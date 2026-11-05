package app.agriwealth.portal

import android.annotation.SuppressLint
import android.app.Activity
import android.app.AlertDialog
import android.content.Intent
import android.app.DownloadManager
import android.graphics.BitmapFactory
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.net.Uri
import android.os.Bundle
import android.os.Environment
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.animation.OvershootInterpolator
import android.view.WindowManager
import android.util.Log
import android.webkit.CookieManager
import android.webkit.DownloadListener
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.webkit.WebView.HitTestResult
import android.webkit.URLUtil
import android.webkit.ValueCallback
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import java.net.HttpURLConnection
import java.net.URL
import java.security.KeyFactory
import java.security.MessageDigest
import java.security.Signature
import java.security.spec.X509EncodedKeySpec
import android.util.Base64
import java.util.Locale

class MainActivity : Activity() {
    private companion object {
        const val FILE_CHOOSER_REQUEST = 4101
        const val DOWNLOAD_PERMISSION_REQUEST = 4102
    }

    private lateinit var config: PortalConfig
    private lateinit var root: FrameLayout
    private lateinit var webView: WebView
    private lateinit var progress: ProgressBar
    private var loaded = false
    private var startupRunning = false
    private var splashFinished = false
    private var welcomeShown = false
    private var portalLoaded = false
    // Remote config polling must not replace the launch overlay while the
    // user is moving through onboarding or splash pages.
    private var launchOverlayVisible = false
    private var preloadedFlashImages: Map<String, android.graphics.Bitmap> = emptyMap()
    private val handler = Handler(Looper.getMainLooper())
    // Check at most once every 15 minutes while the app is visible. A cached
    // or bundled config is used immediately, so this never blocks startup.
    private val configPollIntervalMs = 5_000L
    private var lastConfigCheckMs = 0L
    private var activityResumed = false
    private var pendingFileCallback: ValueCallback<Array<Uri>?>? = null
    private var pendingDownload: DownloadRequest? = null

    private data class DownloadRequest(
        val url: String,
        val userAgent: String?,
        val contentDisposition: String?,
        val mimeType: String?
    )

    // Host the supplied JSON at this path, or change it to your remote config URL.
    private val remoteConfigUrl by lazy { getString(R.string.remote_config_url) }
    private val remoteConfigVersionUrl by lazy {
        remoteConfigUrl.replace("file=portal-config.json", "file=portal-config.version")
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE)
        // Use the last verified remote config, then the trusted bundled config,
        // while a fresh remote check runs in the background.
        config = loadFallbackConfig()
        applyChrome()
        showStartupLoading()
        // Start immediately from cache/bundled config; the remote check is
        // allowed to fail in the background when the device is offline.
        startWithRemoteConfig()
    }

    private val internetRetry = object : Runnable {
        override fun run() {
            if (isFinishing || startupRunning) return
            if (hasInternetConnection()) startWithRemoteConfig()
            else handler.postDelayed(this, 2000)
        }
    }

    private fun hasInternetConnection(): Boolean {
        val manager = getSystemService(CONNECTIVITY_SERVICE) as ConnectivityManager
        val network = manager.activeNetwork ?: return false
        val capabilities = manager.getNetworkCapabilities(network) ?: return false
        // A connected network can be usable before Android finishes its
        // captive-portal/validation probe. Do not block the launch screens
        // just because VALIDATED is temporarily absent.
        return capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    private fun startWithRemoteConfig() {
        if (startupRunning || isFinishing) return
        startupRunning = true
        handler.removeCallbacks(internetRetry)
        fetchRemoteConfig { remoteConfig ->
            runOnUiThread {
                startupRunning = false
                if (remoteConfig != null) config = remoteConfig
                if (config.startUrl.isBlank()) {
                    showStatusMessage("Website is not configured")
                    scheduleConfigPoll()
                    return@runOnUiThread
                }
                applyChrome()
                PushManager.configure(this@MainActivity, config)
                if (config.debuggerDetection.equals("block", true) && android.os.Debug.isDebuggerConnected()) {
                    blockedScreen("Debugging is not allowed")
                } else if (config.maintenance) {
                    maintenanceScreen()
                } else {
                    // Resolve configured images before creating the launch UI
                    // so admin-panel images are visible on the first frame.
                    preloadFlashScreens { images ->
                        if (!isFinishing && !isDestroyed) {
                            preloadedFlashImages = images
                            showPortal()
                            showEnvironmentWarnings()
                        }
                    }
                }
                scheduleConfigPoll()
            }
        }
    }

    private fun showStartupLoading() {
        val page = FrameLayout(this).apply { setBackgroundColor(Color.WHITE) }
        val column = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            gravity = Gravity.CENTER
        }
        column.addView(ProgressBar(this).apply {
            indeterminateTintList = android.content.res.ColorStateList.valueOf(Color.rgb(54, 198, 180))
        }, android.widget.LinearLayout.LayoutParams(dp(52), dp(52)))
        column.addView(TextView(this).apply {
            text = "লোড হচ্ছে..."; textSize = 16f; gravity = Gravity.CENTER
            setTextColor(Color.rgb(82, 98, 118))
        }, android.widget.LinearLayout.LayoutParams(-2, -2).apply { topMargin = dp(16) })
        page.addView(column, FrameLayout.LayoutParams(-1, -1))
        setContentView(page)
    }

    private fun preloadFlashScreens(done: (Map<String, android.graphics.Bitmap>) -> Unit) {
        FlashImagePreloader.preload(config) { images ->
            runOnUiThread {
                if (isFinishing || isDestroyed) return@runOnUiThread
                done(images)
            }
        }
    }

    private fun showStatusMessage(message: String) {
        val page = FrameLayout(this).apply { setBackgroundColor(Color.WHITE) }
        page.addView(TextView(this).apply {
            text = message
            textSize = 22f
            gravity = Gravity.CENTER
            setTextColor(Color.rgb(16, 35, 63))
            typeface = Typeface.DEFAULT_BOLD
        }, FrameLayout.LayoutParams(-1, -1))
        setContentView(page)
    }

    private fun fetchRemoteConfig(done: (PortalConfig?) -> Unit) {
        Thread {
            var connection: HttpURLConnection? = null
            var result: PortalConfig? = null
            try {
                lastConfigCheckMs = System.currentTimeMillis()
                // A changing query value avoids stale intermediary/CDN copies.
                val separator = if (remoteConfigUrl.contains("?")) "&" else "?"
                val url = URL("$remoteConfigUrl${separator}_config_ts=${System.currentTimeMillis()}")
                require(url.protocol == "http" || url.protocol == "https") { "Remote config URL must use HTTP or HTTPS" }
                connection = url.openConnection() as HttpURLConnection
                connection.connectTimeout = 5000
                connection.readTimeout = 5000
                connection.instanceFollowRedirects = true
                connection.setRequestProperty("Accept", "application/json")
                connection.setRequestProperty("Cache-Control", "no-cache, no-store")
                connection.setRequestProperty("Pragma", "no-cache")
                if (connection.responseCode in 200..299) {
                    val body = connection.inputStream.use { it.readBytes() }
                    val signatureUrl = "$remoteConfigUrl.sig" + if (remoteConfigUrl.contains("?")) "&_config_ts=${System.currentTimeMillis()}" else "?_config_ts=${System.currentTimeMillis()}"
                    val signatureConnection = (URL(signatureUrl).openConnection() as HttpURLConnection).apply {
                        connectTimeout = 5000
                        readTimeout = 5000
                        instanceFollowRedirects = true
                        setRequestProperty("Cache-Control", "no-cache, no-store")
                    }
                    try {
                        require(signatureConnection.responseCode in 200..299) { "Signature file unavailable" }
                        val signatureText = signatureConnection.inputStream.bufferedReader().use { it.readText() }.trim()
                        val keyBytes = Base64.decode(getString(R.string.config_signing_public_key), Base64.DEFAULT)
                        val publicKey = KeyFactory.getInstance("RSA").generatePublic(X509EncodedKeySpec(keyBytes))
                        val verifier = Signature.getInstance("SHA256withRSA")
                        verifier.initVerify(publicKey)
                        verifier.update(body)
                        require(verifier.verify(Base64.decode(signatureText, Base64.DEFAULT))) { "Config signature is invalid" }
                    } finally {
                        signatureConnection.disconnect()
                    }
                    result = PortalConfig.parse(String(body, Charsets.UTF_8))
                    getPreferences(MODE_PRIVATE).edit()
                        .putString("remote_config_version", sha256Hex(body))
                        .apply()
                    connection.getHeaderField("ETag")?.let { etag ->
                        getPreferences(MODE_PRIVATE).edit().putString("remote_config_etag", etag).apply()
                    }
                    getPreferences(MODE_PRIVATE).edit()
                        .putString("verified_remote_config", String(body, Charsets.UTF_8))
                        .apply()
                    Log.i("PortalConfig", "Loaded remote config from ${connection.url}")
                } else {
                    Log.w("PortalConfig", "Remote config returned HTTP ${connection.responseCode} from $url")
                }
            } catch (error: Exception) {
                Log.e("PortalConfig", "Could not load verified remote config", error)
            } finally {
                connection?.disconnect()
            }
            done(result)
        }.start()
    }

    private fun checkRemoteConfig(done: (Boolean) -> Unit) {
        Thread {
            var connection: HttpURLConnection? = null
            var changed = false
            try {
                val separator = if (remoteConfigVersionUrl.contains("?")) "&" else "?"
                val url = URL("$remoteConfigVersionUrl${separator}_config_ts=${System.currentTimeMillis()}")
                connection = url.openConnection() as HttpURLConnection
                connection.connectTimeout = 5000
                connection.readTimeout = 5000
                connection.instanceFollowRedirects = true
                connection.setRequestProperty("Accept", "text/plain")
                connection.setRequestProperty("Cache-Control", "no-cache, no-store")
                if (connection.responseCode in 200..299) {
                    val remoteVersion = connection.inputStream.bufferedReader().use { it.readText().trim() }
                    val localVersion = getPreferences(MODE_PRIVATE).getString("remote_config_version", null)
                    changed = localVersion == null || remoteVersion != localVersion
                }
            } catch (error: Exception) {
                Log.w("PortalConfig", "Could not check remote config version", error)
            } finally {
                lastConfigCheckMs = System.currentTimeMillis()
                connection?.disconnect()
            }
            done(changed)
        }.start()
    }

    private fun sha256Hex(bytes: ByteArray): String = MessageDigest.getInstance("SHA-256")
        .digest(bytes).joinToString("") { "%02x".format(it) }

    private val configPoll = object : Runnable {
        override fun run() {
            if (!activityResumed || isFinishing || isDestroyed) return
            if (startupRunning) {
                scheduleConfigPoll()
                return
            }
            startupRunning = true
            checkRemoteConfig { changed ->
                if (!changed) {
                    runOnUiThread {
                        startupRunning = false
                        scheduleConfigPoll()
                    }
                    return@checkRemoteConfig
                }
                fetchRemoteConfig { remote ->
                    runOnUiThread {
                        startupRunning = false
                    if (remote != null && remote != config && !isFinishing && !isDestroyed) {
                        Log.i("PortalConfig", "Remote config changed; applying updated settings")
                        config = remote
                        applyChrome()
                        PushManager.configure(this@MainActivity, config)
                        if (config.startUrl.isBlank()) {
                            showStatusMessage("Website is not configured")
                            scheduleConfigPoll()
                            return@runOnUiThread
                        }
                        // Keep the current onboarding/splash instance alive.
                        // The new config is used as soon as that flow finishes.
                        if (launchOverlayVisible) {
                            scheduleConfigPoll()
                            return@runOnUiThread
                        }
                        if (config.debuggerDetection.equals("block", true) && android.os.Debug.isDebuggerConnected()) {
                            blockedScreen("Debugging is not allowed")
                        } else if (config.maintenance) maintenanceScreen()
                        else {
                            preloadFlashScreens { images ->
                                if (!isFinishing && !isDestroyed) {
                                    preloadedFlashImages = images
                                    showPortal()
                                    showEnvironmentWarnings()
                                }
                            }
                        }
                    }
                    scheduleConfigPoll()
                    }
                }
            }
        }
    }

    private fun scheduleConfigPoll() {
        handler.removeCallbacks(configPoll)
        if (activityResumed && !isFinishing && !isDestroyed) {
            val elapsed = System.currentTimeMillis() - lastConfigCheckMs
            handler.postDelayed(configPoll, (configPollIntervalMs - elapsed).coerceAtLeast(0L))
        }
    }

    private fun loadFallbackConfig(): PortalConfig {
        val preferences = getPreferences(MODE_PRIVATE)
        val cached = preferences.getString("verified_remote_config", null)
        if (!cached.isNullOrBlank()) {
            try { return PortalConfig.parse(cached) } catch (_: Exception) {
                preferences.edit().remove("verified_remote_config").apply()
            }
        }
        return try {
            assets.open("portal-config.json").bufferedReader().use { PortalConfig.parse(it.readText()) }
        } catch (error: Exception) {
            Log.e("PortalConfig", "Bundled config unavailable", error)
            PortalConfig.parse("{\"start_url\":\"\"}")
        }
    }

    override fun onResume() {
        super.onResume()
        activityResumed = true
        scheduleConfigPoll()
    }

    override fun onPause() {
        activityResumed = false
        handler.removeCallbacks(configPoll)
        super.onPause()
    }

    private fun applyChrome() {
        window.statusBarColor = color(config.statusBar, Color.rgb(16, 35, 63))
        window.navigationBarColor = color(config.navigationBar, Color.rgb(8, 23, 43))
        if (config.blockScreenshots) window.setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE)
        else window.clearFlags(WindowManager.LayoutParams.FLAG_SECURE)
        title = config.appName
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun showPortal() {
        welcomeShown = false
        portalLoaded = false
        splashFinished = !config.splashEnabled
        root = FrameLayout(this).apply { setBackgroundColor(Color.WHITE) }
        webView = WebView(this)
        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            loadsImagesAutomatically = true
            mixedContentMode = if (config.httpsOnly) WebSettings.MIXED_CONTENT_NEVER_ALLOW else WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE
            allowFileAccess = false
            javaScriptCanOpenWindowsAutomatically = false
            setSupportMultipleWindows(false)
            userAgentString = "$userAgentString SecureWebPortal/1.0"
        }
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)
        // Suppress Android's native long-press link preview. Normal taps still
        // navigate through the WebViewClient below.
        webView.isLongClickable = true
        webView.setOnLongClickListener {
            // Consume every long-press while link inspection is disabled.
            // Some WebView elements report UNKNOWN instead of an anchor hit,
            // which otherwise lets Android show its native link preview.
            if (!config.inspectLinks) return@setOnLongClickListener true
            val hit = webView.hitTestResult
            if (hit?.type in setOf(HitTestResult.SRC_ANCHOR_TYPE, HitTestResult.SRC_IMAGE_ANCHOR_TYPE)) {
                true
            } else config.disableSelection
        }
        webView.webViewClient = object : WebViewClient() {
            private fun allowInWebView(uri: Uri): Boolean {
                val scheme = uri.scheme?.lowercase(Locale.US)
                if (scheme != "https" && !(scheme == "http" && !config.httpsOnly)) return false
                val host = uri.host ?: return false
                if (config.allows(host)) return true
                if (config.allowExternalLinks) {
                    try { startActivity(Intent(Intent.ACTION_VIEW, uri)) } catch (_: Exception) { }
                }
                return false
            }

            @Suppress("DEPRECATION")
            override fun shouldOverrideUrlLoading(view: WebView, url: String): Boolean {
                return !allowInWebView(Uri.parse(url))
            }

            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                // Keep web links inside the app; block unsupported schemes and
                // HTTP links only when the HTTPS-only policy is enabled.
                return !allowInWebView(request.url)
            }
            override fun onPageFinished(view: WebView, url: String?) {
                loaded = true
                progress.visibility = View.GONE
                view.evaluateJavascript(
                    "(function(){var id='secure-portal-text-selection';var s=document.getElementById(id);if(!s){s=document.createElement('style');s.id=id;(document.head||document.documentElement).appendChild(s);}s.textContent='a,a *{-webkit-touch-callout:none !important;}'+${if (config.disableSelection) "'*,*:before,*:after{-webkit-user-select:none !important;user-select:none !important;}'" else "''"};})();",
                    null
                )
            }

            override fun onReceivedError(
                view: WebView,
                request: android.webkit.WebResourceRequest,
                error: android.webkit.WebResourceError
            ) {
                super.onReceivedError(view, request, error)
                if (request.isForMainFrame) {
                    showWebViewError(error.description?.toString().orEmpty())
                }
            }

            @Suppress("DEPRECATION")
            override fun onReceivedError(view: WebView, errorCode: Int, description: String?, failingUrl: String?) {
                super.onReceivedError(view, errorCode, description, failingUrl)
                if (failingUrl == config.startUrl) showWebViewError(description.orEmpty())
            }

            override fun onReceivedHttpError(
                view: WebView,
                request: WebResourceRequest,
                errorResponse: android.webkit.WebResourceResponse
            ) {
                super.onReceivedHttpError(view, request, errorResponse)
                if (request.isForMainFrame) {
                    showWebViewError("HTTP ${errorResponse.statusCode}")
                }
            }
        }
        webView.webChromeClient = object : WebChromeClient() {
            override fun onShowFileChooser(
                view: WebView?,
                filePathCallback: ValueCallback<Array<Uri>?>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                pendingFileCallback?.onReceiveValue(null)
                pendingFileCallback = filePathCallback
                if (filePathCallback == null) return false
                val intent = Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
                    addCategory(Intent.CATEGORY_OPENABLE)
                    // The admin may configure any website. Its file input may
                    // need any file type, so do not narrow the native picker.
                    type = "*/*"
                    putExtra(
                        Intent.EXTRA_ALLOW_MULTIPLE,
                        fileChooserParams?.mode == FileChooserParams.MODE_OPEN_MULTIPLE
                    )
                }
                return try {
                    startActivityForResult(intent, FILE_CHOOSER_REQUEST)
                    true
                } catch (_: Exception) {
                    pendingFileCallback?.onReceiveValue(null)
                    pendingFileCallback = null
                    false
                }
            }

            override fun onProgressChanged(view: WebView?, value: Int) {
                progress.progress = value
                progress.visibility = if (value < 100) View.VISIBLE else View.GONE
            }
        }
        webView.setDownloadListener(DownloadListener { url, userAgent, contentDisposition, mimeType, _ ->
            startDownload(DownloadRequest(url, userAgent, contentDisposition, mimeType))
        })
        progress = ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal).apply {
            max = 100
            progressDrawable = android.graphics.drawable.ClipDrawable(
                GradientDrawable().apply { setColor(color(config.progressColor, Color.CYAN)) }, Gravity.START, android.graphics.drawable.ClipDrawable.HORIZONTAL
            )
        }
        root.addView(webView, FrameLayout.LayoutParams(-1, -1))
        root.addView(progress, FrameLayout.LayoutParams(-1, dp(3), Gravity.TOP))
        setContentView(root)
        when {
            config.onboardingEnabled && config.onboardingPages.isNotEmpty() -> showOnboarding()
            config.splashEnabled -> showSplash()
            else -> {
                loadPortalIfNeeded()
                maybeShowWelcomePopup()
            }
        }
    }

    private fun loadPortalIfNeeded() {
        if (portalLoaded || config.startUrl.isBlank()) return
        portalLoaded = true
        webView.loadUrl(config.startUrl)
    }

    /** Shows the remote-configured onboarding before the WebView becomes interactive. */
    private fun showOnboarding() {
        launchOverlayVisible = true
        val previousSystemUi = window.decorView.systemUiVisibility
        if (config.onboardingFullscreen) {
            window.decorView.systemUiVisibility = View.SYSTEM_UI_FLAG_FULLSCREEN or View.SYSTEM_UI_FLAG_LAYOUT_STABLE
        }
        lateinit var overlay: OnboardingView
        overlay = OnboardingView(
            this,
            config.onboardingPages,
            config.onboardingSkipText,
            config.onboardingNextText,
            config.onboardingStartedText,
            color(config.accentColor, Color.rgb(103, 87, 217)),
            preloadedFlashImages
        ) {
            root.removeView(overlay)
            launchOverlayVisible = false
            window.decorView.systemUiVisibility = previousSystemUi
            // The onboarding slider is the first launch screen. The splash
            // sequence must run after it and before the WebView is revealed.
            if (config.splashEnabled) showSplash() else {
                splashFinished = true
                loadPortalIfNeeded()
                maybeShowWelcomePopup()
            }
        }
        root.addView(overlay, FrameLayout.LayoutParams(-1, -1))
    }

    private fun maybeShowWelcomePopup() {
        if (!config.welcomeEnabled || !splashFinished || welcomeShown || isFinishing || isDestroyed) return
        welcomeShown = true
        val content = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            setPadding(dp(20), 0, dp(20), 0)
        }
        if (config.welcomeImageUrl.isNotBlank()) {
            val image = ImageView(this).apply {
                adjustViewBounds = true
                scaleType = ImageView.ScaleType.FIT_CENTER
                setBackgroundColor(Color.TRANSPARENT)
                contentDescription = config.welcomeTitle
            }
            content.addView(image, android.widget.LinearLayout.LayoutParams(-1, dp(220)).apply {
                bottomMargin = dp(12)
            })
            loadWelcomeImage(config.welcomeImageUrl, image)
        }
        content.addView(TextView(this).apply {
            text = config.welcomeMessage
            textSize = 16f
            setTextColor(Color.rgb(82, 98, 118))
            setPadding(0, dp(4), 0, dp(4))
        }, android.widget.LinearLayout.LayoutParams(-1, -2))
        val dialog = AlertDialog.Builder(this)
            .setTitle(config.welcomeTitle)
            .setView(content)
            .setPositiveButton(config.welcomeButtonText, null)
            .create()
        dialog.setOnShowListener {
            dialog.getButton(AlertDialog.BUTTON_POSITIVE)?.setTextColor(color(config.accentColor, Color.rgb(54, 198, 180)))
        }
        dialog.show()
    }

    private fun loadWelcomeImage(url: String, image: ImageView) {
        Thread {
            try {
                val parsed = URL(url)
                require(parsed.protocol == "http" || parsed.protocol == "https")
                val boundsConnection = openSplashConnection(url)
                val bounds = try {
                    boundsConnection.inputStream.use { stream ->
                        BitmapFactory.Options().apply { inJustDecodeBounds = true }
                            .also { BitmapFactory.decodeStream(stream, null, it) }
                    }
                } finally {
                    boundsConnection.disconnect()
                }
                if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return@Thread
                val connection = openSplashConnection(url)
                val bitmap = try {
                    connection.inputStream.use { stream ->
                        val options = BitmapFactory.Options().apply {
                            inSampleSize = splashSampleSize(bounds.outWidth, bounds.outHeight)
                            inPreferredConfig = android.graphics.Bitmap.Config.RGB_565
                        }
                        BitmapFactory.decodeStream(stream, null, options)
                    }
                } finally {
                    connection.disconnect()
                }
                if (bitmap != null && !isFinishing && !isDestroyed) {
                    runOnUiThread { if (!isFinishing && !isDestroyed) image.setImageBitmap(bitmap) }
                }
            } catch (error: Exception) {
                Log.w("WelcomeImage", "Could not load welcome image", error)
            }
        }.start()
    }

    private fun showSplash() {
        launchOverlayVisible = true
        val previousSystemUi = window.decorView.systemUiVisibility
        if (config.splashFullscreen) {
            window.decorView.systemUiVisibility = (View.SYSTEM_UI_FLAG_FULLSCREEN or View.SYSTEM_UI_FLAG_LAYOUT_STABLE)
        }
        val overlay = FrameLayout(this)
        root.addView(overlay, FrameLayout.LayoutParams(-1, -1))
        val pages = config.splashPages.take(3)
        if (pages.isEmpty()) {
            launchOverlayVisible = false
            splashFinished = true
            loadPortalIfNeeded()
            maybeShowWelcomePopup()
            return
        }

        val slider = android.widget.ViewAnimator(this)
        overlay.addView(slider, FrameLayout.LayoutParams(-1, -1))
        val pageImages = mutableListOf<ImageView>()
        pages.forEachIndexed { index, page ->
            val pageView = FrameLayout(this).apply {
                val start = color(page.backgroundColor, color(config.splashColor, Color.rgb(16, 35, 63)))
                background = GradientDrawable(
                    GradientDrawable.Orientation.TL_BR,
                    intArrayOf(start, color(config.navigationBar, Color.rgb(8, 23, 43)))
                )
            }
            val image = ImageView(this).apply {
                scaleType = ImageView.ScaleType.CENTER_CROP
                alpha = 0f
            }
            val scrim = View(this).apply { setBackgroundColor(Color.parseColor("#40000000")) }
            val fallbackContent = android.widget.LinearLayout(this).apply {
                orientation = android.widget.LinearLayout.VERTICAL
                gravity = Gravity.CENTER
                alpha = 0f
                scaleX = .82f
                scaleY = .82f
            }
            val logo = TextView(this).apply {
                text = "✦"
                gravity = Gravity.CENTER
                setTextColor(Color.WHITE)
                textSize = 38f
                typeface = Typeface.DEFAULT_BOLD
                background = rounded("#35FFFFFF")
                elevation = dp(8).toFloat()
            }
            fallbackContent.addView(logo, android.widget.LinearLayout.LayoutParams(dp(92), dp(92)))
            val brandShade = View(this).apply {
                background = GradientDrawable(
                    GradientDrawable.Orientation.TOP_BOTTOM,
                    intArrayOf(Color.TRANSPARENT, Color.argb(205, 0, 0, 0))
                )
            }
            val brand = android.widget.LinearLayout(this).apply {
                orientation = android.widget.LinearLayout.VERTICAL
                gravity = Gravity.BOTTOM
                setPadding(dp(28), dp(70), dp(28), dp(64))
            }
            fallbackContent.animate().alpha(1f).scaleX(1f).scaleY(1f).setDuration(720)
                .setStartDelay(120L * index).start()
            pageView.addView(image, FrameLayout.LayoutParams(-1, -1))
            pageView.addView(scrim, FrameLayout.LayoutParams(-1, -1))
            pageView.addView(fallbackContent, FrameLayout.LayoutParams(-1, -1))
            pageView.addView(brandShade, FrameLayout.LayoutParams(-1, dp(250), Gravity.BOTTOM))
            pageView.addView(brand, FrameLayout.LayoutParams(-1, dp(250), Gravity.BOTTOM))
            slider.addView(pageView)
            pageImages.add(image)
            if (page.imageUrl.isNotBlank()) {
                preloadedFlashImages[page.imageUrl]?.let { bitmap ->
                    image.setImageBitmap(bitmap)
                    image.alpha = 1f
                    fallbackContent.alpha = 0f
                    fallbackContent.visibility = View.GONE
                    bounceSplashImage(image)
                } ?: loadSplashImage(page.imageUrl, image, fallbackContent)
            }
        }

        val indicators = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER
        }
        val indicatorViews = pages.indices.map {
            View(this).apply { background = rounded("#80FFFFFF") }
        }
        indicatorViews.forEach { indicator ->
            indicators.addView(indicator, android.widget.LinearLayout.LayoutParams(dp(24), dp(3)).apply {
                leftMargin = dp(4); rightMargin = dp(4)
            })
        }
        overlay.addView(indicators, FrameLayout.LayoutParams(-2, dp(20), Gravity.BOTTOM or Gravity.CENTER_HORIZONTAL).apply {
            bottomMargin = dp(34)
        })

        lateinit var finishSplash: () -> Unit
        val skip = TextView(this).apply {
            text = "Skip"
            textSize = 14f
            setTextColor(Color.WHITE)
            gravity = Gravity.CENTER
            setPadding(dp(18), dp(8), dp(18), dp(8))
            background = rounded("#66000000")
            elevation = dp(4).toFloat()
            setOnClickListener { finishSplash() }
        }
        if (pages.size > 1) {
            overlay.addView(skip, FrameLayout.LayoutParams(-2, -2, Gravity.TOP or Gravity.END).apply {
                topMargin = dp(22); marginEnd = dp(18)
            })
        }

        var pageIndex = 0
        var touchDownX = 0f
        var touchDownY = 0f
        var finished = false
        lateinit var autoAdvance: Runnable
        fun scheduleAutoAdvance() {
            handler.removeCallbacks(autoAdvance)
            if (!finished) handler.postDelayed(autoAdvance, pages[pageIndex].durationMs.coerceIn(1500, 10000))
        }

        fun updateIndicators() {
            indicatorViews.forEachIndexed { index, indicator ->
                indicator.background = rounded(if (index == pageIndex) "#FFFFFFFF" else "#80FFFFFF")
                indicator.layoutParams = (indicator.layoutParams as android.widget.LinearLayout.LayoutParams).apply {
                    width = if (index == pageIndex) dp(34) else dp(24)
                }
                indicator.requestLayout()
            }
        }

        lateinit var navigate: (Int, Int) -> Unit
        navigate = { target, direction ->
            if (!finished) {
                if (target !in pages.indices) {
                    if (target >= pages.size) finishSplash()
                } else {
                    handler.removeCallbacks(autoAdvance)
                    if (target == pageIndex) {
                        scheduleAutoAdvance()
                    } else {
                        pageIndex = target
                            val slideDistance = (overlay.width.takeIf { it > 0 }
                                ?: resources.displayMetrics.widthPixels).toFloat()
                        slider.inAnimation = android.view.animation.TranslateAnimation(
                                direction * slideDistance, 0f, 0f, 0f
                        ).apply { duration = 320 }
                        slider.outAnimation = android.view.animation.TranslateAnimation(
                                0f, -direction * slideDistance, 0f, 0f
                        ).apply { duration = 320 }
                        slider.displayedChild = pageIndex
                            updateIndicators()
                        scheduleAutoAdvance()
                    }
                }
            }
        }
        autoAdvance = object : Runnable {
            override fun run() { navigate(pageIndex + 1, 1) }
        }
        finishSplash = {
            if (!finished) {
                finished = true
                handler.removeCallbacks(autoAdvance)
                root.removeView(overlay)
                launchOverlayVisible = false
                window.decorView.systemUiVisibility = previousSystemUi
                splashFinished = true
                loadPortalIfNeeded()
                maybeShowWelcomePopup()
            }
        }

        slider.setOnTouchListener { _, event ->
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    touchDownX = event.x; touchDownY = event.y
                    handler.removeCallbacks(autoAdvance)
                    true
                }
                MotionEvent.ACTION_UP -> {
                    val dx = event.x - touchDownX
                    val dy = event.y - touchDownY
                    if (kotlin.math.abs(dx) > dp(45) && kotlin.math.abs(dx) > kotlin.math.abs(dy)) {
                        navigate(if (dx < 0) pageIndex + 1 else pageIndex - 1, if (dx < 0) 1 else -1)
                    } else {
                        scheduleAutoAdvance()
                    }
                    true
                }
                MotionEvent.ACTION_CANCEL -> { scheduleAutoAdvance(); true }
                else -> true
            }
        }
        slider.displayedChild = 0
        pageImages[0].contentDescription = "Splash page 1"
        slider.getChildAt(0)?.animate()?.alpha(1f)?.setDuration(450)?.start()
        updateIndicators()
        scheduleAutoAdvance()
    }

    private fun showWebViewError(details: String) {
        if (isFinishing || isDestroyed) return
        launchOverlayVisible = false
        val page = FrameLayout(this).apply { setBackgroundColor(Color.WHITE) }
        val column = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setPadding(dp(28), dp(28), dp(28), dp(28))
        }
        column.addView(TextView(this).apply {
            text = "পোর্টাল লোড করা যায়নি"
            textSize = 23f
            gravity = Gravity.CENTER
            setTextColor(Color.rgb(16, 35, 63))
            typeface = Typeface.DEFAULT_BOLD
        })
        column.addView(TextView(this).apply {
            text = "ইন্টারনেট, start URL অথবা server configuration পরীক্ষা করুন।"
            textSize = 16f
            gravity = Gravity.CENTER
            setTextColor(Color.rgb(82, 98, 118))
            setPadding(0, dp(12), 0, 0)
        })
        if (details.isNotBlank()) column.addView(TextView(this).apply {
            text = details
            textSize = 12f
            gravity = Gravity.CENTER
            setTextColor(Color.rgb(120, 125, 140))
            setPadding(0, dp(8), 0, 0)
        })
        column.addView(TextView(this).apply {
            text = "আবার চেষ্টা করুন"
            textSize = 16f
            gravity = Gravity.CENTER
            setTextColor(Color.WHITE)
            setPadding(dp(28), dp(12), dp(28), dp(12))
            background = rounded(config.refreshColor)
            setOnClickListener { showPortal() }
        }, android.widget.LinearLayout.LayoutParams(-2, -2).apply { topMargin = dp(22) })
        page.addView(column, FrameLayout.LayoutParams(-1, -1))
        setContentView(page)
    }

    private fun openSplashConnection(url: String): HttpURLConnection {
        return (URL(url).openConnection() as HttpURLConnection).apply {
            connectTimeout = 5000
            readTimeout = 5000
            instanceFollowRedirects = true
            setRequestProperty("User-Agent", "Mozilla/5.0 SecureWebPortal/1.0")
            setRequestProperty("Accept", "image/avif,image/webp,image/apng,image/*,*/*;q=0.8")
            require(responseCode in 200..299) { "Splash image returned HTTP $responseCode" }
        }
    }

    private fun loadSplashImage(url: String, image: ImageView, fallback: View) {
        Thread {
            try {
                val bounds = openSplashConnection(url).let { connection ->
                    try {
                        connection.inputStream.use { stream ->
                            BitmapFactory.Options().apply { inJustDecodeBounds = true }
                                .also { options -> BitmapFactory.decodeStream(stream, null, options) }
                        }
                    } finally { connection.disconnect() }
                }
                if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return@Thread
                val bitmap = openSplashConnection(url).let { connection ->
                    try {
                        connection.inputStream.use { stream ->
                            val options = BitmapFactory.Options().apply {
                                inSampleSize = splashSampleSize(bounds.outWidth, bounds.outHeight)
                                inPreferredConfig = android.graphics.Bitmap.Config.RGB_565
                            }
                            BitmapFactory.decodeStream(stream, null, options)
                        }
                    } finally { connection.disconnect() }
                }
                if (bitmap != null) runOnUiThread {
                    if (!isFinishing && !isDestroyed && image.isAttachedToWindow) {
                        image.setImageBitmap(bitmap)
                        image.alpha = 1f
                        bounceSplashImage(image)
                        fallback.animate().alpha(0f).setDuration(220).withEndAction {
                            fallback.visibility = View.GONE
                        }.start()
                    }
                }
            } catch (error: Exception) {
                Log.w("SplashImage", "Could not load splash image in background", error)
            }
        }.start()
    }

    private fun bounceSplashImage(image: ImageView) {
        image.animate().cancel()
        image.scaleX = .9f
        image.scaleY = .9f
        image.animate()
            .scaleX(1.06f)
            .scaleY(1.06f)
            .setDuration(280)
            .setInterpolator(android.view.animation.DecelerateInterpolator())
            .withEndAction {
                image.animate()
                    .scaleX(1f)
                    .scaleY(1f)
                    .setDuration(430)
                    .setInterpolator(OvershootInterpolator(1.25f))
                    .start()
            }
            .start()
    }

    private fun splashSampleSize(width: Int, height: Int): Int {
        val target = maxOf(resources.displayMetrics.widthPixels, resources.displayMetrics.heightPixels) * 2
        var sample = 1
        while (width / sample > target || height / sample > target) sample *= 2
        return sample
    }

    private fun showEnvironmentWarnings() {
        val warnings = mutableListOf<String>()
        if (config.rootDetection.equals("warn", true) && isRooted()) warnings.add("Rooted device detected")
        if (config.emulatorDetection.equals("warn", true) && isEmulator()) warnings.add("Emulator detected")
        if (warnings.isNotEmpty()) Toast.makeText(this, warnings.joinToString(" • "), Toast.LENGTH_LONG).show()
    }

    private fun isRooted(): Boolean {
        val tags = android.os.Build.TAGS.orEmpty()
        val paths = arrayOf("/system/app/Superuser.apk", "/system/xbin/su", "/system/bin/su", "/sbin/su")
        return tags.contains("test-keys") || paths.any { java.io.File(it).exists() }
    }

    private fun isEmulator(): Boolean {
        val fingerprint = android.os.Build.FINGERPRINT.orEmpty()
        val model = android.os.Build.MODEL.orEmpty()
        val product = android.os.Build.PRODUCT.orEmpty()
        return fingerprint.startsWith("generic", true) || fingerprint.contains("emulator", true) ||
            model.contains("Emulator", true) || model.contains("Android SDK built for", true) ||
            product.contains("sdk", true) || product.contains("emulator", true)
    }

    private fun maintenanceScreen() {
        val box = FrameLayout(this).apply { setBackgroundColor(Color.WHITE) }
        val column = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setPadding(dp(28), dp(28), dp(28), dp(28))
        }
        fun text(value: String, size: Float, bold: Boolean = false): TextView = TextView(this).apply {
            text = value; textSize = size; gravity = Gravity.CENTER; setTextColor(Color.rgb(25, 43, 65))
            if (bold) typeface = Typeface.DEFAULT_BOLD
        }
        column.addView(text(config.maintenanceIcon, 48f))
        column.addView(text(config.maintenanceTitle, 24f, true))
        column.addView(text(config.maintenanceMessage, 16f))
        if (config.retryEnabled) column.addView(TextView(this).apply {
            text = "Retry"; textSize = 16f; gravity = Gravity.CENTER; setTextColor(Color.WHITE)
            setPadding(dp(28), dp(12), dp(28), dp(12)); background = rounded(config.refreshColor)
            setOnClickListener { recreate() }
        }, android.widget.LinearLayout.LayoutParams(-2, -2).apply { topMargin = dp(22) })
        box.addView(column, FrameLayout.LayoutParams(-1, -1))
        setContentView(box)
    }

    private fun blockedScreen(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show()
        finish()
    }

    private fun dp(value: Int) = (value * resources.displayMetrics.density).toInt()
    private fun color(value: String, fallback: Int): Int = try { Color.parseColor(value) } catch (_: Exception) { fallback }
    private fun rounded(hex: String) = GradientDrawable().apply { setColor(color(hex, Color.rgb(54, 198, 180))); cornerRadius = dp(8).toFloat() }

    override fun onBackPressed() {
        if (::webView.isInitialized && webView.canGoBack()) webView.goBack() else super.onBackPressed()
    }

    private fun startDownload(request: DownloadRequest) {
        if (request.url.isBlank() || !request.url.startsWith("http", ignoreCase = true)) {
            Toast.makeText(this, "এই ফাইলটি ডাউনলোড করা যাবে না", Toast.LENGTH_SHORT).show()
            return
        }
        if (android.os.Build.VERSION.SDK_INT <= 28 &&
            checkSelfPermission(android.Manifest.permission.WRITE_EXTERNAL_STORAGE) != android.content.pm.PackageManager.PERMISSION_GRANTED
        ) {
            pendingDownload = request
            requestPermissions(arrayOf(android.Manifest.permission.WRITE_EXTERNAL_STORAGE), DOWNLOAD_PERMISSION_REQUEST)
            return
        }
        enqueueDownload(request)
    }

    private fun enqueueDownload(request: DownloadRequest) {
        try {
            val downloadManager = getSystemService(DOWNLOAD_SERVICE) as DownloadManager
            val download = DownloadManager.Request(Uri.parse(request.url)).apply {
                setTitle(URLUtil.guessFileName(request.url, request.contentDisposition, request.mimeType))
                setDescription(config.appName)
                setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                setAllowedOverMetered(true)
                setAllowedOverRoaming(true)
                if (!request.userAgent.isNullOrBlank()) addRequestHeader("User-Agent", request.userAgent)
                CookieManager.getInstance().getCookie(request.url)?.let { addRequestHeader("Cookie", it) }
                setMimeType(request.mimeType ?: "application/octet-stream")
                setDestinationInExternalPublicDir(
                    Environment.DIRECTORY_DOWNLOADS,
                    URLUtil.guessFileName(request.url, request.contentDisposition, request.mimeType)
                )
            }
            downloadManager.enqueue(download)
            Toast.makeText(this, "ডাউনলোড শুরু হয়েছে", Toast.LENGTH_SHORT).show()
        } catch (error: Exception) {
            Log.e("SecureWebPortal", "Download failed", error)
            Toast.makeText(this, "ডাউনলোড শুরু করা যায়নি", Toast.LENGTH_LONG).show()
        }
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode != FILE_CHOOSER_REQUEST) return
        val callback = pendingFileCallback ?: return
        pendingFileCallback = null
        if (resultCode != RESULT_OK || data == null) {
            callback.onReceiveValue(null)
            return
        }
        val results = WebChromeClient.FileChooserParams.parseResult(resultCode, data)
        callback.onReceiveValue(results)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode != DOWNLOAD_PERMISSION_REQUEST) return
        val request = pendingDownload
        pendingDownload = null
        if (grantResults.firstOrNull() == android.content.pm.PackageManager.PERMISSION_GRANTED && request != null) {
            enqueueDownload(request)
        } else {
            Toast.makeText(this, "ডাউনলোডের জন্য storage permission প্রয়োজন", Toast.LENGTH_LONG).show()
        }
    }

    override fun onDestroy() {
        handler.removeCallbacksAndMessages(null)
        if (::webView.isInitialized) webView.destroy()
        super.onDestroy()
    }
}
