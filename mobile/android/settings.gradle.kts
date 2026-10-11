pluginManagement {
    val flutterSdkPath =
        run {
            val properties = java.util.Properties()
            file("local.properties").inputStream().use { properties.load(it) }
            val flutterSdkPath = properties.getProperty("flutter.sdk")
            require(flutterSdkPath != null) { "flutter.sdk not set in local.properties" }
            flutterSdkPath
        }

    includeBuild("$flutterSdkPath/packages/flutter_tools/gradle")

    repositories {
        google()
        mavenCentral()
        gradlePluginPortal()
    }
}

plugins {
    id("dev.flutter.flutter-plugin-loader") version "1.0.0"
    id("com.android.application") version "9.1.0" apply false
    id("org.jetbrains.kotlin.android") version "2.4.0" apply false
    // Lets Gradle download a JDK when a dependency pins a toolchain we do not
    // have locally. flutter_callkit_incoming requires exactly Java 17, so a
    // machine with only a newer JDK (Android Studio ships 21) otherwise fails
    // with "Cannot find a Java installation ... matching {languageVersion=17}".
    id("org.gradle.toolchains.foojay-resolver-convention") version "0.8.0"
}

include(":app")
