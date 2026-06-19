# DeepSeek Phase A2 — White launcher icon (quick)

> Local task (Flutter, PC). The app launcher icon is a deep-blue temple on a deep-blue
> background → blue-on-blue, looks muddy. Make the temple **WHITE** on the blue background.
> When done, delete this file, rebuild a RELEASE apk, report "A2 done" + the apk path.

## 1. Use the white temple as the icon foreground
```bash
cd edifis-mobile
cp ../edifis-brand/logo/generated/icon-white.png assets/brand/icon-white.png
```
In `pubspec.yaml`, in the `flutter_launcher_icons:` block, change the foreground (and legacy image)
to the white temple, keep the navy background:
```yaml
flutter_launcher_icons:
  android: "ic_launcher"
  ios: false
  image_path: "assets/brand/icon-white.png"
  adaptive_icon_background: "#0F2350"
  adaptive_icon_foreground: "assets/brand/icon-white.png"
  min_sdk_android: 21
```
Regenerate:
```bash
dart run flutter_launcher_icons
```

## 2. Rebuild the small release APK
```bash
flutter analyze        # clean
flutter build apk --release --split-per-abi
```
The icon-on-blue should now be a crisp **white** temple. Report "A2 done" + the path to
`build/app/outputs/flutter-apk/app-arm64-v8a-release.apk` so the architect can re-upload it.
(Do NOT commit any .apk; DO commit the pubspec + new asset.)
