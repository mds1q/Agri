package app.agriwealth.portal

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.util.Log
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.ConcurrentHashMap
import java.util.concurrent.CountDownLatch
import java.util.concurrent.Executors

/** Loads both remote flash-screen groups before either screen is shown. */
object FlashImagePreloader {
    fun preload(config: PortalConfig, done: (Map<String, Bitmap>) -> Unit) {
        val urls = (config.onboardingPages.map { it.imageUrl } + config.splashPages.take(3).map { it.imageUrl })
            .filter { it.isNotBlank() }.distinct()
        if (urls.isEmpty()) { done(emptyMap()); return }
        Thread {
            val images = ConcurrentHashMap<String, Bitmap>()
            val workers = Executors.newFixedThreadPool(minOf(4, urls.size))
            val remaining = CountDownLatch(urls.size)
            urls.forEach { url -> workers.execute {
                try { load(url)?.let { images[url] = it } }
                catch (error: Exception) { Log.w("FlashImagePreloader", "Could not preload flash image", error) }
                finally { remaining.countDown() }
            } }
            remaining.await()
            workers.shutdown()
            done(images)
        }.start()
    }

    private fun load(url: String): Bitmap? {
        val bounds = open(url).let { connection ->
            try {
                connection.inputStream.use { stream ->
                    BitmapFactory.Options().apply { inJustDecodeBounds = true }
                        .also { options -> BitmapFactory.decodeStream(stream, null, options) }
                }
            } finally { connection.disconnect() }
        }
        if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return null
        return open(url).let { connection ->
            try {
                connection.inputStream.use { stream ->
                    val options = BitmapFactory.Options().apply {
                        inSampleSize = sampleSize(bounds.outWidth, bounds.outHeight)
                        inPreferredConfig = Bitmap.Config.RGB_565
                    }
                    BitmapFactory.decodeStream(stream, null, options)
                }
            } finally { connection.disconnect() }
        }
    }

    private fun open(url: String): HttpURLConnection = (URL(url).openConnection() as HttpURLConnection).apply {
        connectTimeout = 8_000; readTimeout = 10_000; instanceFollowRedirects = true
        setRequestProperty("User-Agent", "Mozilla/5.0 SecureWebPortal/1.0")
        setRequestProperty("Accept", "image/avif,image/webp,image/apng,image/*,*/*;q=0.8")
        require(responseCode in 200..299) { "Flash image returned HTTP $responseCode" }
    }

    private fun sampleSize(width: Int, height: Int): Int {
        val target = maxOf(android.content.res.Resources.getSystem().displayMetrics.widthPixels,
            android.content.res.Resources.getSystem().displayMetrics.heightPixels) * 2
        var sample = 1
        while (width / sample > target || height / sample > target) sample *= 2
        return sample
    }
}
