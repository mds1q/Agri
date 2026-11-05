package app.agriwealth.portal

import android.animation.ValueAnimator
import android.animation.Animator
import android.animation.AnimatorListenerAdapter
import android.content.Context
import android.graphics.Bitmap
import android.graphics.Color
import android.graphics.drawable.GradientDrawable
import android.graphics.drawable.RippleDrawable
import android.content.res.ColorStateList
import android.app.Activity
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.animation.DecelerateInterpolator
import android.view.animation.OvershootInterpolator
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import java.net.HttpURLConnection
import java.net.URL

/** A small, dependency-free onboarding slider. All content comes from PortalConfig. */
class OnboardingView(
    context: Context,
    private val pages: List<OnboardingPage>,
    private val skipText: String,
    private val nextText: String,
    private val startedText: String,
    private val defaultAccent: Int,
    private val preloadedImages: Map<String, Bitmap>,
    private val onFinished: () -> Unit
) : FrameLayout(context) {
    private val track = LinearLayout(context).apply { orientation = LinearLayout.HORIZONTAL }
    private val dots = LinearLayout(context).apply {
        orientation = LinearLayout.HORIZONTAL
        gravity = Gravity.CENTER
    }
    private val action = TextView(context)
    private var current = 0
    private var downX = 0f
    private var downY = 0f
    private var startOffset = 0f
    private var dragging = false
    private var animator: ValueAnimator? = null
    private val autoSlideDelayMs = 4_000L
    private val autoSlide = object : Runnable {
        override fun run() {
            if (!isAttachedToWindow || pages.size < 2) return
            goTo(if (current == pages.lastIndex) 0 else current + 1)
            postDelayed(this, autoSlideDelayMs)
        }
    }

    private val systemBarColor = Color.rgb(247, 248, 252)

    override fun onSizeChanged(width: Int, height: Int, oldWidth: Int, oldHeight: Int) {
        super.onSizeChanged(width, height, oldWidth, oldHeight)
        if (width <= 0 || height <= 0) return
        // Give the horizontal strip its full content width. Leaving the strip
        // at viewport width can make the first page render correctly while
        // later pages remain outside the measured area on some Android
        // versions.
        (track.layoutParams as? FrameLayout.LayoutParams)?.let { params ->
            params.width = width * maxOf(1, track.childCount)
            params.height = height
            track.layoutParams = params
        }
        // A horizontal LinearLayout can measure several MATCH_PARENT children
        // inconsistently. Give every page the exact viewport width so page 4
        // and later translations always land on the correct content.
        for (index in 0 until track.childCount) {
            track.getChildAt(index).layoutParams =
                (track.getChildAt(index).layoutParams as LinearLayout.LayoutParams).apply {
                    this.width = width
                    this.height = height
                }
        }
        track.requestLayout()
        track.translationX = -width.toFloat() * current
    }

    init {
        clipChildren = false
        addView(track, LayoutParams(LayoutParams.MATCH_PARENT, LayoutParams.MATCH_PARENT))
        pages.forEachIndexed { index, page ->
            track.addView(createPage(page, index), LinearLayout.LayoutParams(LayoutParams.MATCH_PARENT, LayoutParams.MATCH_PARENT))
        }
        addView(dots, LayoutParams(LayoutParams.WRAP_CONTENT, dp(24), Gravity.BOTTOM or Gravity.CENTER_HORIZONTAL).apply {
            bottomMargin = dp(112)
        })
        val skip = TextView(context).apply {
            text = skipText
            textSize = 14f
            setTextColor(Color.rgb(95, 99, 120))
            setPadding(dp(12), dp(8), dp(12), dp(8))
            setOnClickListener { finish() }
        }
        addView(skip, LayoutParams(LayoutParams.WRAP_CONTENT, LayoutParams.WRAP_CONTENT, Gravity.TOP or Gravity.END).apply {
            topMargin = dp(20); marginEnd = dp(18)
        })
        action.apply {
            text = nextText
            textSize = 16f
            gravity = Gravity.CENTER
            setTextColor(Color.WHITE)
            typeface = android.graphics.Typeface.DEFAULT_BOLD
            elevation = dp(5).toFloat()
            background = rippleRounded(defaultAccent)
            setOnClickListener {
                removeCallbacks(autoSlide)
                if (current == pages.lastIndex) finish() else goTo(current + 1)
                scheduleAutoSlide()
            }
            setOnTouchListener { _, event ->
                when (event.actionMasked) {
                    MotionEvent.ACTION_DOWN -> {
                        animate().scaleX(.96f).scaleY(.96f).setDuration(90).start()
                    }
                    MotionEvent.ACTION_UP, MotionEvent.ACTION_CANCEL -> {
                        animate().scaleX(1f).scaleY(1f).setDuration(180)
                            .setInterpolator(OvershootInterpolator(1.2f)).start()
                    }
                }
                false
            }
        }
        addView(action, LayoutParams(dp(150), dp(54), Gravity.BOTTOM or Gravity.CENTER_HORIZONTAL).apply {
            bottomMargin = dp(40)
        })
        pages.indices.forEach { index ->
            val dot = View(context).apply { background = rounded(Color.rgb(215, 216, 228)) }
            dots.addView(dot, LinearLayout.LayoutParams(dp(if (index == 0) 28 else 8), dp(8)).apply {
                marginStart = dp(4); marginEnd = dp(4)
            })
        }
        updateChrome()
        updateSystemBars(parseColor(pages.firstOrNull()?.backgroundColor ?: "#F7F8FC", systemBarColor))
        post {
            animatePage(0, 1)
            scheduleAutoSlide()
        }
        // Keep the moving strip from exposing a gap while a finger is held
        // beyond the first or last page.
        track.clipChildren = false
    }

    // Intercept only a clear horizontal gesture. This keeps the Next and
    // Skip controls clickable while making swipes work over every page child,
    // including TextViews and the illustration view.
    override fun onInterceptTouchEvent(event: MotionEvent): Boolean {
        when (event.actionMasked) {
            MotionEvent.ACTION_DOWN -> {
                downX = event.x
                downY = event.y
                startOffset = track.translationX
                dragging = false
                animator?.cancel()
                removeCallbacks(autoSlide)
            }
            MotionEvent.ACTION_MOVE -> {
                val dx = event.x - downX
                val dy = event.y - downY
                if (kotlin.math.abs(dx) > dp(10) && kotlin.math.abs(dx) > kotlin.math.abs(dy) * 1.15f) {
                    dragging = true
                    return true
                }
            }
        }
        return false
    }

    override fun onTouchEvent(event: MotionEvent): Boolean {
        when (event.actionMasked) {
            MotionEvent.ACTION_MOVE -> {
                if (!dragging) return true
                val delta = event.x - downX
                val minOffset = -width.toFloat() * pages.lastIndex
                val maxOffset = 0f
                val rawOffset = startOffset + delta
                track.translationX = when {
                    rawOffset > maxOffset -> maxOffset + (rawOffset - maxOffset) * .22f
                    rawOffset < minOffset -> minOffset + (rawOffset - minOffset) * .22f
                    else -> rawOffset
                }
            }
            MotionEvent.ACTION_UP -> {
                val delta = event.x - downX
                if (dragging && kotlin.math.abs(delta) > dp(48)) {
                    goTo(if (delta < 0) current + 1 else current - 1)
                } else {
                    goTo(current)
                }
                dragging = false
                scheduleAutoSlide()
            }
            MotionEvent.ACTION_CANCEL -> {
                goTo(current)
                dragging = false
                scheduleAutoSlide()
            }
        }
        return true
    }

    private fun scheduleAutoSlide() {
        removeCallbacks(autoSlide)
        if (pages.size > 1 && isAttachedToWindow) postDelayed(autoSlide, autoSlideDelayMs)
    }

    private fun createPage(page: OnboardingPage, index: Int): View {
        val pageView = LinearLayout(context).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER_HORIZONTAL
            setPadding(dp(28), dp(62), dp(28), dp(176))
            setBackgroundColor(parseColor(page.backgroundColor, Color.rgb(247, 248, 252)))
            alpha = 1f
        }
        val art = FrameLayout(context)
        val image = ImageView(context).apply { scaleType = ImageView.ScaleType.CENTER_INSIDE; visibility = View.GONE }
        art.addView(image, FrameLayout.LayoutParams(dp(292), dp(292), Gravity.CENTER))
        pageView.addView(art, LinearLayout.LayoutParams(-1, 0, 1f))
        pageView.addView(TextView(context).apply {
            text = page.title; textSize = 28f; gravity = Gravity.CENTER
            setTextColor(Color.rgb(28, 31, 52)); typeface = android.graphics.Typeface.DEFAULT_BOLD
            includeFontPadding = false
        }, LinearLayout.LayoutParams(-1, -2))
        pageView.addView(TextView(context).apply {
            text = page.message; textSize = 16f; gravity = Gravity.CENTER
            setTextColor(Color.rgb(104, 107, 128)); setPadding(dp(8), dp(14), dp(8), 0)
            includeFontPadding = false
            maxLines = 3
        }, LinearLayout.LayoutParams(-1, -2))
        if (page.imageUrl.isNotBlank()) {
            preloadedImages[page.imageUrl]?.let {
                image.setImageBitmap(it)
                image.visibility = View.VISIBLE
            } ?: loadImage(page.imageUrl, image, index)
        }
        return pageView
    }

    private fun loadImage(url: String, image: ImageView, pageIndex: Int) {
        Thread {
            try {
                val bounds = withImageConnection(url) { connection ->
                    connection.inputStream.use { stream ->
                        android.graphics.BitmapFactory.Options().apply { inJustDecodeBounds = true }
                            .also { android.graphics.BitmapFactory.decodeStream(stream, null, it) }
                    }
                }
                if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return@Thread
                val bitmap = withImageConnection(url) { connection ->
                    connection.inputStream.use { stream ->
                        val options = android.graphics.BitmapFactory.Options().apply {
                            inSampleSize = sampleSize(bounds.outWidth, bounds.outHeight, dp(292) * 2)
                            inPreferredConfig = android.graphics.Bitmap.Config.RGB_565
                        }
                        android.graphics.BitmapFactory.decodeStream(stream, null, options)
                    }
                }
                if (bitmap != null) post {
                    image.setImageBitmap(bitmap)
                    image.visibility = View.VISIBLE
                    if (current == pageIndex) bounceImage(image) else {
                        image.alpha = 1f
                        image.scaleX = 1f
                        image.scaleY = 1f
                    }
                }
            } catch (_: Exception) { }
        }.start()
    }

    private fun <T> withImageConnection(url: String, block: (HttpURLConnection) -> T): T {
        val connection = (URL(url).openConnection() as HttpURLConnection).apply {
            connectTimeout = 8000
            readTimeout = 10000
            instanceFollowRedirects = true
            setRequestProperty("User-Agent", "Mozilla/5.0 SecureWebPortal/1.0")
            setRequestProperty("Accept", "image/avif,image/webp,image/apng,image/*,*/*;q=0.8")
        }
        return try { block(connection) } finally { connection.disconnect() }
    }

    private fun sampleSize(width: Int, height: Int, target: Int): Int {
        var sample = 1
        while (width / sample > target || height / sample > target) sample *= 2
        return sample
    }

    private fun goTo(target: Int) {
        val next = target.coerceIn(0, pages.lastIndex)
        val pageWidth = width
        if (pageWidth <= 0 || track.childCount == 0) {
            post { goTo(next) }
            return
        }
        if (next == current && track.translationX == -pageWidth.toFloat() * current) return
        val previous = current
        current = next
        animator?.cancel()
        val from = track.translationX
        val to = -pageWidth.toFloat() * current
        animator = ValueAnimator.ofFloat(from, to).apply {
            duration = 360; interpolator = DecelerateInterpolator()
            addUpdateListener { track.translationX = it.animatedValue as Float }
            addListener(object : AnimatorListenerAdapter() {
                override fun onAnimationEnd(animation: Animator) {
                    animator = null
                }
            })
            start()
        }
        animatePage(current, if (next >= previous) 1 else -1)
        updateChrome()
    }

    private fun animatePage(index: Int, direction: Int) {
        val page = track.getChildAt(index) ?: return
        val content = page as? LinearLayout
        val art = content?.getChildAt(0)
        val title = content?.getChildAt(1)
        val message = content?.getChildAt(2)
        page.animate().cancel()
        page.alpha = .86f
        page.scaleX = .985f
        page.scaleY = .985f
        page.translationY = dp(10 * direction).toFloat()
        page.animate()
            .alpha(1f)
            .scaleX(1f)
            .scaleY(1f)
            .translationY(0f)
            .setDuration(460)
            .setInterpolator(DecelerateInterpolator())
            .start()

        art?.animate()?.cancel()
        art?.alpha = .72f
        art?.scaleX = .92f
        art?.scaleY = .92f
        art?.translationY = dp(18 * direction).toFloat()
        art?.animate()?.alpha(1f)?.scaleX(1f)?.scaleY(1f)?.translationY(0f)
            ?.setStartDelay(40)?.setDuration(520)
            ?.setInterpolator(OvershootInterpolator(1.05f))?.start()

        listOf(title to 100L, message to 170L).forEach { (view, delay) ->
            view?.animate()?.cancel()
            view?.alpha = 0f
            view?.translationY = dp(16).toFloat()
            view?.animate()?.alpha(1f)?.translationY(0f)?.setStartDelay(delay)
                ?.setDuration(360)?.setInterpolator(DecelerateInterpolator())?.start()
        }

        val image = (art as? FrameLayout)?.getChildAt(0) as? ImageView
        if (image?.visibility == View.VISIBLE) bounceImage(image)
    }

    private fun bounceImage(image: ImageView) {
        image.animate().cancel()
        image.scaleX = .86f
        image.scaleY = .86f
        image.alpha = 0f
        image.animate()
            .alpha(1f)
            .scaleX(1.08f)
            .scaleY(1.08f)
            .setDuration(260)
            .setInterpolator(DecelerateInterpolator())
            .withEndAction {
                image.animate()
                    .scaleX(1f)
                    .scaleY(1f)
                    .setDuration(420)
                    .setInterpolator(OvershootInterpolator(1.35f))
                    .start()
            }
            .start()
    }

    private fun updateChrome() {
        if (pages.isEmpty()) return
        val accent = parseColor(pages[current].accentColor, defaultAccent)
        action.background = rippleRounded(accent)
        action.text = if (current == pages.lastIndex) startedText else nextText
        if (current == pages.lastIndex) {
            action.animate().scaleX(1.06f).scaleY(1.06f).setDuration(180).withEndAction {
                action.animate().scaleX(1f).scaleY(1f).setDuration(180).start()
            }.start()
        } else {
            action.animate().scaleX(1f).scaleY(1f).setDuration(160).start()
        }
        for (index in 0 until dots.childCount) {
            val dot = dots.getChildAt(index)
            val params = dot.layoutParams as LinearLayout.LayoutParams
            val targetWidth = dp(if (index == current) 28 else 8)
            val oldWidth = params.width
            if (oldWidth != targetWidth) {
                ValueAnimator.ofInt(oldWidth, targetWidth).apply {
                    duration = 220
                    interpolator = DecelerateInterpolator()
                    addUpdateListener { value ->
                        dot.layoutParams = (dot.layoutParams as LinearLayout.LayoutParams).apply {
                            width = value.animatedValue as Int
                        }
                    }
                    start()
                }
            }
            dot.background = rounded(if (index == current) accent else Color.rgb(215, 216, 228))
        }
        updateSystemBars(parseColor(pages[current].backgroundColor, systemBarColor))
    }

    private fun updateSystemBars(background: Int) {
        val activity = context as? Activity ?: return
        activity.window.statusBarColor = background
        activity.window.navigationBarColor = background
        val luminance = (Color.red(background) * 299 + Color.green(background) * 587 + Color.blue(background) * 114) / 1000
        var flags = activity.window.decorView.systemUiVisibility
        flags = if (luminance > 180) {
            flags or View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR or View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR
        } else {
            flags and View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR.inv() and View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR.inv()
        }
        activity.window.decorView.systemUiVisibility = flags
    }

    private fun finish() {
        removeCallbacks(autoSlide)
        animator?.cancel()
        animator = null
        onFinished()
    }

    override fun onDetachedFromWindow() {
        removeCallbacks(autoSlide)
        animator?.cancel()
        super.onDetachedFromWindow()
    }
    private fun rounded(color: Int) = GradientDrawable().apply { setColor(color); cornerRadius = dp(30).toFloat() }
    private fun rippleRounded(color: Int): RippleDrawable {
        return RippleDrawable(ColorStateList.valueOf(Color.argb(45, 255, 255, 255)), rounded(color), null)
    }
    private fun parseColor(value: String, fallback: Int) = try { Color.parseColor(value) } catch (_: Exception) { fallback }
    private fun dp(value: Int) = (value * resources.displayMetrics.density).toInt()
}

