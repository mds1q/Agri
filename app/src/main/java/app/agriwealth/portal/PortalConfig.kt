package app.agriwealth.portal

import org.json.JSONArray
import org.json.JSONObject
import java.net.URI
import java.util.Locale

data class SplashPage(
    val imageUrl: String,
    val backgroundColor: String,
    val durationMs: Long
)

data class OnboardingPage(
    val title: String,
    val message: String,
    val imageUrl: String,
    val backgroundColor: String,
    val accentColor: String
)

data class PushConfig(
    val enabled: Boolean,
    val provider: String,
    val oneSignalAppId: String,
    val firebaseProjectId: String,
    val firebaseApplicationId: String,
    val firebaseApiKey: String,
    val firebaseSenderId: String,
    val channelId: String,
    val channelName: String,
    val requestPermission: Boolean
)

data class PortalConfig(
    val appName: String,
    val startUrl: String,
    val allowedDomains: List<String>,
    val splashEnabled: Boolean,
    val splashImageUrl: String,
    val splashColor: String,
    val splashDuration: Long,
    val splashFullscreen: Boolean,
    val splashPages: List<SplashPage>,
    val onboardingEnabled: Boolean,
    val onboardingFullscreen: Boolean,
    val onboardingPages: List<OnboardingPage>,
    val onboardingSkipText: String,
    val onboardingNextText: String,
    val onboardingStartedText: String,
    val statusBar: String,
    val navigationBar: String,
    val progressColor: String,
    val refreshColor: String,
    val accentColor: String,
    val maintenance: Boolean,
    val maintenanceTitle: String,
    val maintenanceMessage: String,
    val maintenanceIcon: String,
    val retryEnabled: Boolean,
    val httpsOnly: Boolean,
    val blockScreenshots: Boolean,
    val rootDetection: String,
    val emulatorDetection: String,
    val debuggerDetection: String,
    val disableSelection: Boolean,
    val allowExternalLinks: Boolean,
    val inspectLinks: Boolean,
    val welcomeEnabled: Boolean,
    val welcomeTitle: String,
    val welcomeMessage: String,
    val welcomeButtonText: String,
    val welcomeImageUrl: String,
    val push: PushConfig
) {
    fun allows(host: String): Boolean {
        val mainHost = try { URI(startUrl).host } catch (_: Exception) { null }
        if (!mainHost.isNullOrBlank() && host.equals(mainHost, true)) return true
        return allowedDomains.any { rule ->
        val normalizedHost = host.trimEnd('.').lowercase(Locale.US)
        val normalizedRule = normalizeDomainRule(rule)
        normalizedRule == "*" || normalizedHost == normalizedRule ||
            (normalizedRule.startsWith("*.") &&
                (normalizedHost == normalizedRule.drop(2) || normalizedHost.endsWith(normalizedRule.drop(1))))
        }
    }

    private fun normalizeDomainRule(rule: String): String {
        val value = rule.trim().lowercase(Locale.US)
        if (value == "*") return value
        val withScheme = if (value.contains("://")) value else "https://$value"
        return try {
            URI(withScheme).host?.trimEnd('.')?.lowercase(Locale.US) ?: value.trimEnd('.')
        } catch (_: Exception) {
            value.trimEnd('.')
        }
    }

    companion object {
        fun parse(json: String): PortalConfig {
            val root = JSONObject(json)
            val splash = root.optJSONObject("splash_screen") ?: JSONObject()
            val colors = root.optJSONObject("ui_colors") ?: JSONObject()
            val maintenance = root.optJSONObject("maintenance_mode") ?: JSONObject()
            val security = root.optJSONObject("security_policies") ?: JSONObject()
            val popups = root.optJSONObject("popups_and_notifications") ?: JSONObject()
            val welcome = popups.optJSONObject("welcome_popup") ?: JSONObject()
            val push = popups.optJSONObject("push_notifications") ?: JSONObject()
            val onboarding = root.optJSONObject("onboarding") ?: JSONObject()
            val domains = root.optJSONArray("allowed_domains") ?: JSONArray().put("*")
            val defaultSplashDuration = splash.optLong("duration_ms", 1200).coerceIn(500, 10000)
            val splashPagesJson = splash.optJSONArray("pages")
            val splashPages = if (splashPagesJson != null && splashPagesJson.length() > 0) {
                val configuredPages = (0 until minOf(splashPagesJson.length(), 3)).mapNotNull { index ->
                    val page = splashPagesJson.optJSONObject(index) ?: return@mapNotNull null
                    SplashPage(
                        // Each admin page owns its own image. Do not copy the
                        // global fallback into every page; that makes blank
                        // pages look like duplicate slides.
                        page.optString("image_url", ""),
                        page.optString("background_color", splash.optString("background_color", "#10233f")),
                        page.optLong("duration_ms", defaultSplashDuration).coerceIn(500, 10000)
                    )
                }
                if (configuredPages.isNotEmpty() && configuredPages.all { it.imageUrl.isBlank() } && splash.optString("image_url", "").isNotBlank()) {
                    listOf(SplashPage(splash.optString("image_url", ""), splash.optString("background_color", "#10233f"), defaultSplashDuration))
                } else {
                    configuredPages
                }
            } else {
                listOf(SplashPage(splash.optString("image_url", ""), splash.optString("background_color", "#10233f"), defaultSplashDuration))
            }
            val onboardingPagesJson = onboarding.optJSONArray("pages")
            val onboardingPages = if (onboardingPagesJson != null && onboardingPagesJson.length() > 0) {
                (0 until minOf(onboardingPagesJson.length(), 4)).mapNotNull { index ->
                    val page = onboardingPagesJson.optJSONObject(index) ?: return@mapNotNull null
                    OnboardingPage(
                        page.optString("title", "স্বাগতম"),
                        page.optString("message", "আপনার কাজ আরও সহজ ও দ্রুত করুন।"),
                        page.optString("image_url", ""),
                        page.optString("background_color", "#F7F8FC"),
                        page.optString("accent_color", colors.optString("accent", "#6757D9"))
                    )
                }.filter { page ->
                    page.title.isNotBlank() || page.message.isNotBlank() || page.imageUrl.isNotBlank()
                }
            } else emptyList()
            return PortalConfig(
                root.optString("app_name", "Secure Web Portal"), root.optString("start_url", ""),
                (0 until domains.length()).map { domains.optString(it) }, splash.optBoolean("enabled", true),
                splash.optString("image_url", ""), splash.optString("background_color", "#10233f"), defaultSplashDuration,
                splash.optBoolean("fullscreen", true), splashPages,
                onboarding.optBoolean("enabled", false), onboarding.optBoolean("fullscreen", false), onboardingPages,
                onboarding.optString("skip_text", "Skip"), onboarding.optString("next_text", "Next"), onboarding.optString("started_text", "Get Started"),
                colors.optString("status_bar", "#10233f"), colors.optString("navigation_bar", "#08172b"),
                colors.optString("progress", "#36c6b4"), colors.optString("refresh", "#36c6b4"), colors.optString("accent", "#36c6b4"),
                maintenance.optBoolean("enabled", false), maintenance.optString("title", "We’ll be back soon"),
                maintenance.optString("message", "Please try again shortly."), maintenance.optString("icon", "🛠️"), maintenance.optBoolean("retry_enabled", true),
                security.optBoolean("https_only", false), security.optBoolean("block_screenshots", true), security.optString("root_detection", "warn"),
                security.optString("emulator_detection", "warn"), security.optString("debugger_detection", "block"),
                security.optBoolean("disable_selection", false), security.optBoolean("allow_external_links", true), security.optBoolean("inspect_links", false),
                welcome.optBoolean("enabled", false), welcome.optString("title", "Welcome"),
                welcome.optString("message", "Welcome back."), welcome.optString("button_text", "OK"),
                welcome.optString("image_url", ""),
                PushConfig(
                    push.optBoolean("enabled", false),
                    push.optString("provider", "none").lowercase(Locale.US),
                    push.optString("onesignal_app_id", ""),
                    push.optString("firebase_project_id", ""),
                    push.optString("firebase_application_id", ""),
                    push.optString("firebase_api_key", ""),
                    push.optString("firebase_sender_id", ""),
                    push.optString("channel_id", "portal_updates"),
                    push.optString("channel_name", "Portal updates"),
                    push.optBoolean("request_permission", true)
                )
            )
        }
    }
}
