allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
subprojects {
    project.evaluationDependsOn(":app")
    // Some plugins (e.g. just_audio) target Java 8, which emits "obsolete
    // source/target value" warnings. The Flutter release build passes -Werror,
    // turning those into hard errors. Strip -Werror from every JavaCompile task
    // so third-party plugin sources compile cleanly. (Lazy configuration —
    // safe without afterEvaluate.)
    tasks.withType<JavaCompile>().configureEach {
        options.compilerArgs = options.compilerArgs.filter { it != "-Werror" }
    }
    // Force a consistent JVM target across Java + Kotlin for all plugins
    // (flutter_timezone/others default to 1.8 Kotlin vs 11 Java → "Inconsistent
    // JVM Target Compatibility" build failure). Align everything to 17.
    tasks.withType<org.jetbrains.kotlin.gradle.tasks.KotlinCompile>().configureEach {
        compilerOptions {
            jvmTarget.set(org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17)
        }
    }
    // Some transitive plugins require a consistent Java 17 across library
    // subprojects (avoids "Inconsistent JVM Target Compatibility").
    plugins.withId("com.android.library") {
        extensions.configure<com.android.build.api.dsl.LibraryExtension> {
            compileOptions {
                sourceCompatibility = JavaVersion.VERSION_17
                targetCompatibility = JavaVersion.VERSION_17
            }
        }
    }
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
