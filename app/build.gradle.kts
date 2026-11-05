import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

val releaseSigningProperties = Properties().apply {
    val propertiesFile = rootProject.file("release-signing.properties")
    if (propertiesFile.exists()) propertiesFile.inputStream().use { load(it) }
}

val configuredRemoteConfigUrl = providers.gradleProperty("remoteConfigUrl")
    .orElse("http://104.243.34.108/~ssvilfyk/api.php?file=portal-config.json")
    .get()

android {
    namespace = "app.agriwealth.portal"
    compileSdk = 35

    defaultConfig {
        applicationId = "app.agriwealth.portal"
        minSdk = 24
        targetSdk = 35
        versionCode = 2
        versionName = "1.0.1"
        resValue("string", "remote_config_url", configuredRemoteConfigUrl)
    }

    signingConfigs {
        create("release") {
            val keyFile = releaseSigningProperties.getProperty("storeFile")
            if (!keyFile.isNullOrBlank()) {
                storeFile = rootProject.file(keyFile)
                storePassword = releaseSigningProperties.getProperty("storePassword")
                keyAlias = releaseSigningProperties.getProperty("keyAlias")
                keyPassword = releaseSigningProperties.getProperty("keyPassword")
                enableV1Signing = true
                enableV2Signing = true
            }
        }
    }

    buildTypes {
        getByName("release") {
            signingConfig = signingConfigs.getByName("release")
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

}

dependencies {
    implementation(platform("com.google.firebase:firebase-bom:35.1.0"))
    implementation("com.google.firebase:firebase-messaging")
    implementation("com.onesignal:OneSignal:5.10.2")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.10.2")
}
