# Task Manager Android Project

This folder contains a structured Android Studio project that wraps your mobile PHP application in a native `WebView`.

## How to Compile into an APK

1.  **Open Android Studio**: Launch Android Studio and select **Open** (or **File > Open**).
2.  **Select this Folder**: Navigate to and select the `android/` folder.
3.  **Wait for Gradle Sync**: Android Studio will automatically start syncing Gradle. This may take a few minutes if it needs to download dependencies.
4.  **Configure the URL**:
    - Open `app/src/main/java/com/taskmanager/app/MainActivity.java`.
    - Find the `APP_URL` variable:
      ```java
      private static final String APP_URL = "http://10.0.2.2/taskmanager/mobile/";
      ```
    - Replace `http://10.0.2.2/taskmanager/mobile/` with your actual hosted URL.
    - **Note**: `10.0.2.2` is the special IP address that allows the Android emulator to access your computer's `localhost`.
5.  **Build the APK**:
    - Go to **Build > Build Bundle(s) / APK(s) > Build APK(s)**.
    - Once finished, a notification will appear. Click **locate** to find the `app-debug.apk` file.

## Project Structure
- `app/src/main/`: Contains the Java code, Android manifest, and resource files.
- `web-source/`: A copy of your original `mobile/` folder for reference.

## Note on PHP
Since this is a native wrapper, the PHP files themselves are NOT bundled inside the APK. The APK acts as a browser that automatically loads your hosted PHP application. This ensures that your database and server-side logic continue to work correctly.
