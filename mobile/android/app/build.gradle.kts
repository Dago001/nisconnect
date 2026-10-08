import java.util.Base64
import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Release signing, from (first match wins):
//  1. android/key.properties (git-ignored): storeFile, storePassword, keyAlias,
//     keyPassword — storeFile is relative to android/app/ or absolute.
//  2. Environment variables (used by .github/workflows/release.yml):
//     ANDROID_KEYSTORE_PATH (or ANDROID_KEYSTORE_BASE64, decoded to a temp
//     file), ANDROID_KEYSTORE_PASSWORD, ANDROID_KEY_ALIAS, ANDROID_KEY_PASSWORD.
// With neither, release builds fall back to the debug key so
// `flutter build apk --release` and CI still work (not for the Play Store).
val keystoreProperties = Properties().apply {
    val file = rootProject.file("key.properties")
    if (file.exists()) file.inputStream().use { load(it) }
}

val releaseKeystore: File? = when {
    keystoreProperties.containsKey("storeFile") -> file(keystoreProperties.getProperty("storeFile"))
    !System.getenv("ANDROID_KEYSTORE_PATH").isNullOrBlank() -> file(System.getenv("ANDROID_KEYSTORE_PATH"))
    !System.getenv("ANDROID_KEYSTORE_BASE64").isNullOrBlank() ->
        layout.buildDirectory.file("signing/release.jks").get().asFile.apply {
            parentFile.mkdirs()
            writeBytes(Base64.getMimeDecoder().decode(System.getenv("ANDROID_KEYSTORE_BASE64").trim()))
        }
    else -> null
}

fun signingValue(property: String, env: String): String? =
    keystoreProperties.getProperty(property) ?: System.getenv(env)?.takeIf { it.isNotBlank() }

android {
    namespace = "ng.gov.immigration.nisconnect"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = "ng.gov.immigration.nisconnect"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        // flutter_secure_storage, local_auth and record need API 23+; LiveKit 24+.
        minSdk = maxOf(flutter.minSdkVersion, 24)
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (releaseKeystore != null) {
            create("release") {
                storeFile = releaseKeystore
                storePassword = signingValue("storePassword", "ANDROID_KEYSTORE_PASSWORD")
                keyAlias = signingValue("keyAlias", "ANDROID_KEY_ALIAS")
                keyPassword = signingValue("keyPassword", "ANDROID_KEY_PASSWORD")
                    ?: signingValue("storePassword", "ANDROID_KEYSTORE_PASSWORD")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.findByName("release") ?: signingConfigs.getByName("debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
