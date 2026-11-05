package app.agriwealth.portal

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.util.Log
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.messaging.FirebaseMessaging
import com.onesignal.OneSignal
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

/** Initializes only the provider selected by the signed remote configuration. */
object PushManager {
    private const val TAG = "PushManager"
    private const val FIREBASE_TOPIC = "all_users"
    private const val NOTIFICATION_PERMISSION_REQUEST = 1807
    private var oneSignalAppId: String? = null
    private var firebaseConfigured = false

    fun configure(activity: Activity, config: PortalConfig) {
        if (!config.push.enabled) return
        activity.getSharedPreferences("portal_push", Context.MODE_PRIVATE).edit()
            .putString("channel_id", config.push.channelId)
            .putString("channel_name", config.push.channelName)
            .apply()
        val provider = config.push.provider.lowercase()
        val useOneSignal = provider == "onesignal" || provider == "both"
        val useFirebase = provider == "firebase" || provider == "both"

        if (useOneSignal && config.push.oneSignalAppId.isNotBlank() && oneSignalAppId != config.push.oneSignalAppId) {
            try {
                OneSignal.initWithContext(activity.applicationContext, config.push.oneSignalAppId)
                oneSignalAppId = config.push.oneSignalAppId
                if (config.push.requestPermission) {
                    CoroutineScope(Dispatchers.Main).launch {
                        OneSignal.Notifications.requestPermission(false)
                    }
                }
            } catch (error: Exception) {
                Log.e(TAG, "OneSignal initialization failed", error)
            }
        }

        if (useFirebase && !firebaseConfigured && hasFirebaseConfig(config.push)) {
            try {
                val app = FirebaseApp.getApps(activity).firstOrNull { it.name == "[DEFAULT]" }
                    ?: FirebaseApp.initializeApp(activity, FirebaseOptions.Builder()
                        .setProjectId(config.push.firebaseProjectId)
                        .setApplicationId(config.push.firebaseApplicationId)
                        .setApiKey(config.push.firebaseApiKey)
                        .setGcmSenderId(config.push.firebaseSenderId)
                        .build())
                    ?: return
                FirebaseMessaging.getInstance().subscribeToTopic(FIREBASE_TOPIC)
                    .addOnFailureListener { error -> Log.e(TAG, "Firebase topic subscription failed", error) }
                firebaseConfigured = true
            } catch (error: Exception) {
                Log.e(TAG, "Firebase initialization failed", error)
            }
        }

        if (config.push.requestPermission && Build.VERSION.SDK_INT >= 33 &&
            activity.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            activity.requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), NOTIFICATION_PERMISSION_REQUEST)
        }
    }

    private fun hasFirebaseConfig(config: PushConfig): Boolean =
        config.firebaseProjectId.isNotBlank() &&
            config.firebaseApplicationId.isNotBlank() &&
            config.firebaseApiKey.isNotBlank() &&
            config.firebaseSenderId.isNotBlank()
}
