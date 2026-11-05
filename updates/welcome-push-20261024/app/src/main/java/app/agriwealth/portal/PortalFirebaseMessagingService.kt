package app.agriwealth.portal

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Color
import android.net.Uri
import android.os.Build
import android.util.Log
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import java.net.HttpURLConnection
import java.net.URL

class PortalFirebaseMessagingService : FirebaseMessagingService() {
    override fun onMessageReceived(message: RemoteMessage) {
        val data = message.data
        val title = message.notification?.title ?: data["title"] ?: "Portal notification"
        val body = message.notification?.body ?: data["body"] ?: ""
        val imageUrl = data["image_url"] ?: message.notification?.imageUrl?.toString()
        val clickUrl = data["click_url"]
        showNotification(title, body, imageUrl, clickUrl)
    }

    override fun onNewToken(token: String) {
        // The app subscribes to the server-managed all_users topic. The token
        // is intentionally not logged or sent to an untrusted endpoint.
        Log.d("PortalPush", "Firebase registration token refreshed")
    }

    private fun showNotification(title: String, body: String, imageUrl: String?, clickUrl: String?) {
        val preferences = getSharedPreferences("portal_push", MODE_PRIVATE)
        val channelId = preferences.getString("channel_id", "portal_updates") ?: "portal_updates"
        val channelName = preferences.getString("channel_name", "Portal updates") ?: "Portal updates"
        val manager = getSystemService(NotificationManager::class.java)
        if (Build.VERSION.SDK_INT >= 26) {
            manager.createNotificationChannel(NotificationChannel(
                channelId, channelName, NotificationManager.IMPORTANCE_DEFAULT
            ).apply { description = "Updates from the portal" })
        }

        val intent = if (!clickUrl.isNullOrBlank() && clickUrl.startsWith("https://")) {
            Intent(Intent.ACTION_VIEW, Uri.parse(clickUrl))
        } else {
            Intent(this, MainActivity::class.java)
        }.apply { flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP }
        val pendingFlags = PendingIntent.FLAG_UPDATE_CURRENT or
            if (Build.VERSION.SDK_INT >= 23) PendingIntent.FLAG_IMMUTABLE else 0
        val pendingIntent = PendingIntent.getActivity(this, 0, intent, pendingFlags)
        val builder = if (Build.VERSION.SDK_INT >= 26) Notification.Builder(this, channelId)
            else Notification.Builder(this)
        builder.setSmallIcon(R.drawable.ic_stat_portal)
            .setColor(Color.rgb(54, 198, 180))
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(Notification.BigTextStyle().bigText(body))
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setPriority(Notification.PRIORITY_DEFAULT)

        val image = loadImage(imageUrl)
        if (image != null) {
            builder.setLargeIcon(image)
            builder.setStyle(Notification.BigPictureStyle().bigPicture(image).setSummaryText(body))
        }
        manager.notify((System.currentTimeMillis() and 0x7fffffff).toInt(), builder.build())
    }

    private fun loadImage(url: String?): Bitmap? {
        if (url.isNullOrBlank() || !url.startsWith("https://")) return null
        return try {
            val connection = (URL(url).openConnection() as HttpURLConnection).apply {
                connectTimeout = 5000
                readTimeout = 7000
                instanceFollowRedirects = true
                setRequestProperty("Accept", "image/png,image/jpeg,image/webp")
            }
            try {
                if (connection.responseCode !in 200..299 || connection.contentLengthLong > 5L * 1024 * 1024) return null
                connection.inputStream.use { BitmapFactory.decodeStream(it) }
            } finally { connection.disconnect() }
        } catch (error: Exception) {
            Log.w("PortalPush", "Notification image could not be loaded", error)
            null
        }
    }
}
