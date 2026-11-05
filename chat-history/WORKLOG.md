# Chat History and Project Work Log

## 2026-10-24 — Local download link updated

### অনুরোধ
- সর্বশেষ release APK local download server-এ उपलब्ध করা।

### সম্পন্ন ধাপ
1. নতুন `SecureWebPortal-release-latest.apk` local download copy-তে রাখা হয়েছে।
2. Download pages-এর existing latest APK link যাচাই করা হয়েছে।

### পরিবর্তিত ফাইল
- `local-downloads/SecureWebPortal-release-latest.apk` — বর্তমান release APK copy।

### যাচাই
- Root APK ও local download APK-এর SHA-256 একই: `aaf3ec20417e6597e2463d2cd78bbea6ff272779e1d609c34a46f0252fb90d90`।

## 2026-10-24 — Bundled config সরিয়ে connection status screen

### অনুরোধ
- APK-এর bundled website/config ব্যবহার না করে remote config ব্যর্থ হলে শুধু `Server connection failed` এবং `start_url` খালি হলে শুধু `Website is not configured` দেখানো।

### সম্পন্ন ধাপ
1. APK থেকে bundled `portal-config.json` ও signature সরানো হয়েছে।
2. Remote config না পাওয়া গেলে নির্দিষ্ট status message দেখানো হয়েছে।
3. Remote config পাওয়া গেলেও `start_url` খালি থাকলে website খোলা বন্ধ করে নির্দিষ্ট status message দেখানো হয়েছে।
4. Startup ও foreground config polling—দুই পথেই একই আচরণ রাখা হয়েছে।
5. Updated release APK তৈরি করা হয়েছে।

### পরিবর্তিত ফাইল
- `app/src/main/java/app/agriwealth/portal/MainActivity.kt` — remote-only config flow ও status screen।
- `app-source-package/app/src/main/java/app/agriwealth/portal/MainActivity.kt` — একই পরিবর্তন।
- `app/src/main/assets/portal-config.json` — সরানো হয়েছে।
- `app/src/main/assets/portal-config.json.sig` — সরানো হয়েছে।
- `app-source-package/app/src/main/assets/portal-config.json` — সরানো হয়েছে।
- `app-source-package/app/src/main/assets/portal-config.json.sig` — সরানো হয়েছে।
- `SecureWebPortal-release-latest.apk` — updated release APK।

### যাচাই
- `gradle :app:assembleRelease -Pandroid.aapt2FromMavenOverride=/tmp/agri-aapt2-dir/aapt2` সফল হয়েছে।
- APK ও build output-এর SHA-256: `aaf3ec20417e6597e2463d2cd78bbea6ff272779e1d609c34a46f0252fb90d90`।
- APK-তে bundled `portal-config` asset নেই।

### পরবর্তী করণীয়
- ডিভাইসে নতুন APK ইনস্টল করে remote config না পাওয়া এবং খালি `start_url`—দুই অবস্থা যাচাই করা।

## 2026-10-24 — WebView file upload/download এবং updated APK

### অনুরোধ
- Admin যে কোনো website সেট করলে সেই website থেকে যেকোনো file upload/download Android WebView-তে চালু করা।
- QEMU দিয়ে AAPT2 চালিয়ে updated APK তৈরি করা।

### সম্পন্ন ধাপ
1. WebView-এর native file chooser যুক্ত করা হয়েছে; যেকোনো MIME type এবং multiple file selection সমর্থিত।
2. WebView download listener যুক্ত করা হয়েছে; website-এর download ফাইল Android Downloads folder-এ সেভ হয় এবং login cookie/user-agent পাঠানো হয়।
3. Android API 28 এবং তার আগের সংস্করণের জন্য প্রয়োজনীয় storage permission যোগ করা হয়েছে।
4. Top-level source এবং `app-source-package` source দুটিতে একই পরিবর্তন রাখা হয়েছে।
5. QEMU x86_64 wrapper দিয়ে AAPT2 চালিয়ে release APK তৈরি করা হয়েছে।

### পরিবর্তিত ফাইল
- `app/src/main/java/app/agriwealth/portal/MainActivity.kt` — WebView file chooser এবং DownloadManager integration।
- `app/src/main/AndroidManifest.xml` — Android 9 এবং আগের জন্য download storage permission।
- `app-source-package/app/src/main/java/app/agriwealth/portal/MainActivity.kt` — একই source update।
- `app-source-package/app/src/main/AndroidManifest.xml` — একই permission update।
- `SecureWebPortal-release-latest.apk` — updated release APK।

### যাচাই
- `gradle :app:assembleRelease -Pandroid.aapt2FromMavenOverride=/tmp/agri-aapt2-dir/aapt2` সফল হয়েছে।
- QEMU wrapper `/tmp/aapt2-qemu` দিয়ে AAPT2 version সফলভাবে চালু হয়েছে।
- APK ও Gradle output-এর SHA-256 একই: `9156c1d50a90c57e2579edf642f62883963fa3583a528d0def46a5b3547de397`।
- APK size: `5070224 bytes`।

### পরবর্তী করণীয়
- বাস্তব Android device-এ একটি file input এবং একটি download link দিয়ে upload/download flow যাচাই করা।

## 2026-10-24 — APK package parsing compatibility fix

### অনুরোধ
- Updated APK ইনস্টল করার সময় package parsing error ঠিক করা।

### কারণ শনাক্ত
- প্রথম updated APK-তে V2 signing থাকলেও V1 `META-INF/CERT.SF` ও `META-INF/CERT.RSA` signature ছিল না। কিছু Android installer এতে parsing error দেখাতে পারে।

### সম্পন্ন ধাপ
1. Release signing configuration-এ V1 এবং V2 দুটো signing explicitly চালু করা হয়েছে।
2. QEMU AAPT2 দিয়ে release APK পুনরায় তৈরি করা হয়েছে।
3. APK-এর package metadata এবং V1 signature entries যাচাই করা হয়েছে।
4. Local download server-এ থাকা latest APK আপডেট হয়েছে।

### পরিবর্তিত ফাইল
- `app/build.gradle.kts` — V1/V2 signing চালু।
- `app-source-package/app/build.gradle.kts` — একই signing configuration।
- `SecureWebPortal-release-latest.apk` — compatibility rebuild।

### যাচাই
- Build: `BUILD SUCCESSFUL`।
- APK-তে `META-INF/CERT.SF` এবং `META-INF/CERT.RSA` আছে।
- `aapt2 dump badging` সফলভাবে package metadata পড়েছে।
- APK ও local download-এর SHA-256 একই: `5bc4f60fdf85910587d8ccece0c6de3e40c0c9cc2b3fe41586fc64ac13aae9eb`।

এই ফাইলটিতে প্রজেক্টে করা কাজের ধাপ, সিদ্ধান্ত, পরিবর্তন এবং পরবর্তী কাজের তথ্য রাখা হবে। প্রতিটি নতুন কাজ শেষে নিচের ফরম্যাটে একটি নতুন এন্ট্রি যোগ করতে হবে।

এই `chat-history` ফোল্ডারটি প্রজেক্টের কাজের কেন্দ্রীয় নথি। নতুন কাজ শুরু করার আগে [`PROJECT-CONTEXT.md`](PROJECT-CONTEXT.md) এবং এই ফাইল পড়তে হবে। কাজ শেষে এখানে সেই কাজের পূর্ণ বিবরণ যোগ করতে হবে, যাতে পরবর্তী সময়ে আগের চ্যাট বা সিদ্ধান্ত ছাড়াই কাজ চালিয়ে যাওয়া যায়।

## কীভাবে ব্যবহার করতে হবে

1. কাজ শুরুর তারিখ লিখুন।
2. ব্যবহারকারীর অনুরোধ সংক্ষেপে লিখুন।
3. কী কী ধাপ করা হয়েছে তা ক্রমানুসারে লিখুন।
4. কোন ফাইল তৈরি বা পরিবর্তন হয়েছে তা উল্লেখ করুন।
5. পরীক্ষা বা যাচাইয়ের ফল লিখুন।
6. অসম্পূর্ণ কাজ থাকলে পরবর্তী করণীয় অংশে লিখুন।

## কাজের এন্ট্রি ফরম্যাট

```md
## YYYY-MM-DD — কাজের শিরোনাম

### অনুরোধ
- কী করতে বলা হয়েছিল

### সম্পন্ন ধাপ
1. প্রথম ধাপ
2. দ্বিতীয় ধাপ

### পরিবর্তিত ফাইল
- `path/to/file` — কী পরিবর্তন হয়েছে

### যাচাই
- কীভাবে যাচাই করা হয়েছে এবং ফলাফল

### পরবর্তী করণীয়
- বাকি কাজ বা ভবিষ্যৎ কাজ
```

## 2026-10-21 — কাজের ইতিহাস সংরক্ষণের ব্যবস্থা

### অনুরোধ
- এই প্রজেক্টে চ্যাটের মাধ্যমে করা কাজের ধাপগুলো সংরক্ষণ করার জন্য একটি ফাইল ও ফোল্ডার তৈরি করা।

### সম্পন্ন ধাপ
1. প্রজেক্টের মূল ডিরেক্টরিতে `chat-history` ফোল্ডার তৈরি করা হয়েছে।
2. কাজের ধারাবাহিক তথ্য রাখার জন্য এই `WORKLOG.md` ফাইল তৈরি করা হয়েছে।
3. ভবিষ্যৎ কাজের জন্য একটি নির্দিষ্ট এন্ট্রি ফরম্যাট যোগ করা হয়েছে।

### পরিবর্তিত ফাইল
- `chat-history/WORKLOG.md` — কাজের ইতিহাসের ফাইল তৈরি করা হয়েছে।

### যাচাই
- ফাইলটি প্রজেক্টের মূল ডিরেক্টরির অধীনে তৈরি হয়েছে এবং Markdown ফরম্যাটে লেখা হয়েছে।

### পরবর্তী করণীয়
- প্রতিটি নতুন কাজ শেষ হলে এই ফাইলে তারিখসহ নতুন এন্ট্রি যোগ করতে হবে।

## 2026-10-21 — Project context যোগ করা

### অনুরোধ
- ভবিষ্যতে এই প্রজেক্টে কাজ দিলে আগের কাজের তথ্য `chat-history` ফোল্ডার থেকে পাওয়া যাবে—এমন ব্যবস্থা করা।

### সম্পন্ন ধাপ
1. প্রজেক্টের স্থায়ী পরিচিতি, কাঠামো, নিয়ম এবং বর্তমান অবস্থার জন্য `PROJECT-CONTEXT.md` তৈরি করা হয়েছে।
2. ভবিষ্যৎ কাজ শুরুর আগে context এবং work log পড়ার নিয়ম এই ফাইলে যোগ করা হয়েছে।
3. `WORKLOG.md`-তে `chat-history` ফোল্ডারকে কেন্দ্রীয় কাজের নথি হিসেবে উল্লেখ করা হয়েছে।

### পরিবর্তিত ফাইল
- `chat-history/PROJECT-CONTEXT.md` — স্থায়ী project context যোগ করা হয়েছে।
- `chat-history/WORKLOG.md` — ভবিষ্যৎ কাজের জন্য পড়া ও লেখার নিয়ম যোগ করা হয়েছে।

### যাচাই
- নতুন context ফাইলটি work log-এর সঙ্গে linked এবং ভবিষ্যৎ কাজের workflow স্পষ্টভাবে লেখা আছে।

### পরবর্তী করণীয়
- প্রতিটি কাজ শেষে এই work log-এ পূর্ণ এন্ট্রি যোগ করতে হবে।

## 2026-10-21 — Onboarding-এর পরে animated splash screen চালু

### অনুরোধ
- বর্তমানে শুধু onboarding slider দেখা যায় এবং পরে blank screen দেখা যায়। Onboarding-এর পরে splash/flash screen চালু করতে হবে, splash-এ সুন্দর animation দিতে হবে এবং admin panel-এর slider configuration ঠিকভাবে কাজ করাতে হবে।

### কারণ শনাক্ত
- `MainActivity.showOnboarding()` শেষ হওয়ার callback সরাসরি WebView চালু করছিল। ফলে `config.splashEnabled` সত্য হলেও onboarding-এর পরে `showSplash()` কখনো কল হতো না।
- Splash page-এ image URL খালি থাকলে fallback কনটেন্ট ছিল খুব সীমিত, তাই screen blank মনে হতে পারত।
- Admin panel থেকে onboarding ও splash page configuration ইতিমধ্যে JSON-এ লেখা হচ্ছিল; প্রধান launch sequence-এ সেটি ব্যবহার হচ্ছিল না।

### সম্পন্ন ধাপ
1. Onboarding শেষ হলে `config.splashEnabled` পরীক্ষা করে `showSplash()` চালু করা হয়েছে।
2. Splash বন্ধ থাকলে তবেই সরাসরি WebView চালু হবে।
3. Splash image না থাকলেও app name ও একটি native fallback mark দেখানো হয়েছে।
4. Fallback mark-এ fade এবং scale animation যোগ করা হয়েছে।
5. Splash শেষ হলে WebView চালু এবং welcome popup দেখানোর আগের workflow অক্ষুণ্ণ রাখা হয়েছে।

### পরিবর্তিত ফাইল
- `app/src/main/java/app/agriwealth/portal/MainActivity.kt` — onboarding → splash → WebView sequence এবং fallback animation ঠিক করা হয়েছে।

### যাচাই
- `gradle :app:assembleDebug` চালানো হয়েছে, কিন্তু AAPT2 daemon startup / SDK XML version environment error-এর কারণে resource processing ধাপে build বন্ধ হয়েছে।
- `gradle :app:compileDebugKotlin -x processDebugResources` চালালে resource বাদ দেওয়ার কারণে `R` reference তৈরি না হওয়ায় compile সম্পূর্ণ হয়নি; এটি source change-এর নির্দিষ্ট error নয়।

### পরবর্তী করণীয়
- Android Studio বা ঠিকমতো চালু AAPT2 SDK environment-এ debug/release APK build করে device-এ sequence যাচাই করতে হবে।
- Admin panel থেকে অন্তত দুইটি onboarding page ও দুইটি splash page save করে APK-তে ক্রমটি দেখা উচিত।

## 2026-10-22 — Onboarding polling এবং white WebView fallback

### কারণ শনাক্ত
- Foreground config polling onboarding বা splash চলাকালীন `showPortal()` চালিয়ে বর্তমান launch overlay প্রতিস্থাপন করতে পারত। এতে onboarding অসম্পূর্ণ থাকা বা সাদা screen দেখা সম্ভব ছিল।
- WebView-এর main frame load error হলে আগে কোনো user-facing error state ছিল না; ফলে শুধু সাদা WebView দেখা যেত।
- Live API config এবং workspace-এর admin storage config আলাদা হলে panel save app-এ আসবে না; APK-কে panel-এর একই public `api.php?file=portal-config.json` endpoint দিয়ে build করতে হবে।

### সম্পন্ন ধাপ
1. Onboarding/splash active থাকলে polling নতুন config memory-তে রাখে, বর্তমান overlay ভাঙে না এবং flow শেষে নতুন config ব্যবহার করে।
2. Main frame network ও HTTP error-এর জন্য Bengali retry screen যোগ করা হয়েছে।

### পরিবর্তিত ফাইল
- `app/src/main/java/app/agriwealth/portal/MainActivity.kt`

## 2026-10-21 — APK output যাচাই

### অনুরোধ
- এই workspace-এই অ্যাপ তৈরি হয় কি না এবং APK কোথায় আছে তা যাচাই করা।

### সম্পন্ন ধাপ
1. Android Gradle output directory পরীক্ষা করা হয়েছে।
2. Release APK-এর মূল output path শনাক্ত করা হয়েছে: `app/build/outputs/apk/release/app-release.apk`।
3. Root ও delivery copy-গুলোর timestamp মিলিয়ে দেখা হয়েছে।
4. বর্তমান output APK-এর timestamp নতুন source fix-এর আগের হওয়ায় নিশ্চিত হওয়া গেছে যে এতে latest splash fix নেই।

### যাচাই
- বর্তমান release output: `app/build/outputs/apk/release/app-release.apk` — 2026-10-21 05:29 UTC।
- Root copy: `SecureWebPortal-release-latest.apk` — 2026-10-21 05:30 UTC।
- নতুন build চালানো হয়েছে, কিন্তু AAPT2 daemon চালু হয়নি। কারণ Android SDK-এর `aapt2` binary এই runtime-এর glibc environment-এ চালানো যাচ্ছে না।

### পরবর্তী করণীয়
- Android Studio, Ubuntu/Debian Linux বা glibc-compatible build runner-এ release APK rebuild করতে হবে।
- নতুন APK তৈরি হলে সেটি `app/build/outputs/apk/release/app-release.apk` থেকে নিয়ে root release copy হিসেবে প্রকাশ করা যাবে।

## 2026-10-21 — Runtime workaround দিয়ে release APK build

### অনুরোধ
- এই workspace-এর runtime চালু করে নতুন release APK build করা।

### সম্পন্ন ধাপ
1. Environment-এ ARM64-compatible `aapt2` runtime শনাক্ত করা হয়েছে।
2. Gradle-কে `android.aapt2FromMavenOverride=/tmp/agri-aapt2-dir/aapt2` দিয়ে release build চালানো হয়েছে।
3. `gradle :app:assembleRelease` সফলভাবে সম্পন্ন হয়েছে।
4. নতুন APK root-এর `SecureWebPortal-release-latest.apk` হিসেবে কপি করা হয়েছে।

### পরিবর্তিত/তৈরি ফাইল
- `app/build/outputs/apk/release/app-release.apk` — নতুন release APK।
- `SecureWebPortal-release-latest.apk` — নতুন release APK-এর root copy।

### যাচাই
- Build result: `BUILD SUCCESSFUL`।
- Package ID: `app.agriwealth.portal`।
- APK size: `896596 bytes`।
- Root copy ও build output-এর SHA-256 একই: `9c5e8fa2c3288c1c71427d9ee1474c7fc0c2b67c4d9cc7cb4e7ef9dc198ded06`।
- `apksigner verify` এই runtime-এর JVM memory-executable restriction-এর কারণে চালু হয়নি; build signing task সফল হয়েছে।

## 2026-10-21 — Release signing key যাচাই

### অনুরোধ
- নতুন release APK-তে আগের সঠিক signing key ব্যবহার করতে হবে; বর্তমান key সঠিক কি না যাচাই করা।

### যাচাই
- `release-signing.properties` বর্তমানে `release-signing-key.p12` এবং alias `securewebportal` ব্যবহার করছে।
- `release-signing-key.p12` এবং `delivery/release-signing-key.p12` byte-for-byte একই।
- দুই key-এর certificate fingerprint একই: `E6:20:19:9B:85:8E:8B:84:48:6C:A0:8C:6C:38:83:C6:60:DD:42:B3:36:0A:74:22:2F:9D:AA:0A:67:5C:12:3F`।
- Workspace-এ এর বাইরে কোনো `.p12`, `.jks` বা `.keystore` পাওয়া যায়নি।

### অবস্থা
- সঠিক আগের keystore এই workspace-এ নেই। সঠিক key file বা তার backup না পাওয়া পর্যন্ত signing key পরিবর্তন করে নিরাপদ release APK তৈরি করা যাবে না।

## 2026-10-21 — পুরোনো debug signing key দিয়ে release APK rebuild

### অনুরোধ
- workspace-এর অন্য জায়গায় থাকা আগের signing key খুঁজে সেটি দিয়ে release APK তৈরি করা।

### কারণ শনাক্ত
- `/root/.android/debug.keystore` পাওয়া গেছে।
- এই keystore-এর SHA-256 certificate fingerprint `C5:33:AF:50:EB:83:0A:B6:10:E3:B7:F3:4B:2D:55:89:B6:51:21:04:9F:36:01:B6:5C:F4:B4:19:11:AB:F7:4C`।
- পুরোনো `SecureWebPortal.apk`-এর certificate fingerprint একই, তাই এটিই আগের key।

### সম্পন্ন ধাপ
1. `release-signing.properties`-এ `/root/.android/debug.keystore`, alias `androiddebugkey`, password `android` সেট করা হয়েছে।
2. ARM64 `aapt2` runtime ব্যবহার করে `gradle :app:assembleRelease` সফলভাবে চালানো হয়েছে।
3. নতুন APK `SecureWebPortal-release-latest.apk` হিসেবে root-এ কপি করা হয়েছে।

### যাচাই
- নতুন release APK এবং পুরোনো `SecureWebPortal.apk`-এর signing certificate fingerprint একই।
- Build result: `BUILD SUCCESSFUL`।
- APK size: `896181 bytes`।
- Build output ও root copy-এর SHA-256 একই: `7be76057231a2ac405c14d68ae42de10914e460a62cb007a5867b21c9b82eb39`।

## 2026-10-21 — Existing package conflict সমাধান

### সমস্যা
- `SecureWebPortal-release-latest.apk` install করার সময় “App not installed as package conflicts with an existing package” দেখিয়েছে।

### কারণ
- নতুন APK debug key fingerprint `C5:33...` দিয়ে signed ছিল।
- আগের public release APK `E6:20...` release key দিয়ে signed ছিল। একই package ID হলেও certificate আলাদা হওয়ায় Android update আটকেছে।

### সম্পন্ন ধাপ
1. `release-signing.properties` আবার canonical `release-signing-key.p12`-এ ফিরিয়ে দেওয়া হয়েছে।
2. Original release key দিয়ে `gradle :app:assembleRelease` সফলভাবে চালানো হয়েছে।
3. নতুন compatible APK `SecureWebPortal-release-latest.apk` হিসেবে local server-এর file আপডেট করা হয়েছে।

### যাচাই
- নতুন APK fingerprint: `E6:20:19:9B:85:8E:8B:84:48:6C:A0:8C:6C:38:83:C6:60:DD:42:B3:36:0A:74:22:2F:9D:AA:0A:67:5C:12:3F`।
- Build result: `BUILD SUCCESSFUL`।
- APK size: `896596 bytes`।
- SHA-256: `673068d5f921e2994e29f4734a974c68ec14e3892d91e76ebbe7562b80673f7c`।

## 2026-10-21 — Admin-এর ৪টি onboarding slide ঠিক করা

### অনুরোধ
- Admin panel-এ যোগ করা ৪টি slider তথ্য app-এ সঠিকভাবে দেখা যাচ্ছে না।

### কারণ শনাক্ত
- Remote admin JSON endpoint পরীক্ষা করে দেখা গেছে ৪টি onboarding page-এর title ও message server থেকে সঠিকভাবে আসছে।
- `OnboardingView`-এর horizontal track-এ প্রতিটি page `MATCH_PARENT` width-এ ছিল; একাধিক page-এর ক্ষেত্রে viewport width নির্ভরযোগ্যভাবে সেট না হওয়ায় শেষের slide translation/rendering সমস্যা করছিল।

### সম্পন্ন ধাপ
1. প্রতিটি onboarding page-এর width ও height screen viewport-এর exact size হিসেবে সেট করা হয়েছে।
2. View layout হওয়ার পর track position পুনরায় sync করা হয়েছে।
3. Layout width zero থাকলে navigation post করে পরে চালানোর fallback যোগ করা হয়েছে।
4. নতুন release APK build করে local download server-এর APK update করা হয়েছে।

### পরিবর্তিত ফাইল
- `app/src/main/java/app/agriwealth/portal/OnboardingView.kt` — ৪টি slide-এর নির্ভরযোগ্য sizing ও navigation fix।
- `SecureWebPortal-release-latest.apk` — নতুন release build।

## 2026-10-22 — Flash screen এবং slider UI polish

### অনুরোধ
- Flash/splash screen এবং onboarding slider-এর visual quality উন্নত করা।

### সম্পন্ন ধাপ
1. Onboarding page-এর background অনুযায়ী status bar ও navigation bar রঙ এবং light/dark icon contrast আপডেট করা হয়েছে।
2. Next/Get Started button-এ touch ripple যোগ করা হয়েছে এবং text spacing, padding ও line limit ঠিক করা হয়েছে।
3. Fallback onboarding illustration-কে simple face icon থেকে shield/check ভিত্তিক polished portal illustration-এ উন্নত করা হয়েছে।
4. Splash screen-এ image থাকলেও নিচে app name ও tagline সহ readable gradient brand panel রাখা হয়েছে।
5. Splash image load করার সময় bounds দেখে downsample করা হয়েছে, যাতে বড় image-এ memory pressure কমে।
6. Release APK rebuild করে latest shared APK copy আপডেট করা হয়েছে।

### পরিবর্তিত ফাইল
- `app/src/main/java/app/agriwealth/portal/OnboardingView.kt` — slider visual polish, system bar handling, button feedback ও illustration উন্নতি।
- `app/src/main/java/app/agriwealth/portal/MainActivity.kt` — splash brand panel, image memory handling ও presentation polish।
- `SecureWebPortal-release-latest.apk` — নতুন release build।

### যাচাই
- `gradle :app:assembleRelease -Pandroid.aapt2FromMavenOverride=/tmp/agri-aapt2-dir/aapt2` সফল হয়েছে।
- APK ও build output-এর SHA-256 একই: `b7a173c1bd232d615cd059b445fc571668dad0fb219c3a1fc967bd1395faa975`।
- APK size: `901374 bytes`।

### পরবর্তী করণীয়
- Device-এ নতুন APK ইনস্টল করে light/dark system bar, image যুক্ত splash এবং ৪টি onboarding slide হাতে দেখে final visual approval নেওয়া।

### যাচাই
- Remote endpoint-এ ৪টি onboarding page পাওয়া গেছে: `১`, `হাই`, `৩`, `৪`।
- Build result: `BUILD SUCCESSFUL`।
- APK SHA-256: `adc0ded2cfb8653dd566f012ce0255e2eb3570190093a631f1c0174b9d1eae15`।

## 2026-10-21 — Professional slider ও flash animation

### অনুরোধ
- Slider ও flash screen-কে professional, সুন্দর এবং user-friendly animation সহ নির্ভরযোগ্যভাবে চালু করা।

### সম্পন্ন ধাপ
1. Onboarding page transition-এ smooth fade, scale এবং vertical easing যোগ করা হয়েছে।
2. প্রথম slide load হওয়ার সময়ও entrance animation যোগ করা হয়েছে।
3. প্রতিটি slide-এর background-এ gradient treatment যোগ করা হয়েছে।
4. Flash screen-এ branded logo, app name এবং fallback subtitle যোগ করা হয়েছে।
5. Flash image এলে crossfade animation হয়; image না এলে fallback content সুন্দরভাবে থাকে।
6. Existing dots, Next/Get Started, Skip এবং swipe interaction বজায় রেখে animation polish করা হয়েছে।
7. নতুন release APK build করে local download server-এর file update করা হয়েছে।

### পরিবর্তিত ফাইল
- `app/src/main/java/app/agriwealth/portal/OnboardingView.kt` — onboarding transition animation।
- `app/src/main/java/app/agriwealth/portal/MainActivity.kt` — gradient flash screen, branded fallback ও image crossfade।
- `SecureWebPortal-release-latest.apk` — নতুন release build।

### যাচাই
- Build result: `BUILD SUCCESSFUL`।
- APK SHA-256: `15d39f423f58c949eb5db4be645f253310565dfe0d17a4c28ca746656e71de85`।
- APK size: `897595 bytes`।

## 2026-10-21 — Local download server চালু

### অনুরোধ
- নতুন release APK-এর জন্য local server download link দেওয়া।

### সম্পন্ন ধাপ
1. `upload_server.py` দিয়ে local HTTP server চালু করা হয়েছে।
2. Release APK-এর direct URL পরীক্ষা করা হয়েছে।

### যাচাই
- Server: `http://localhost:8000/`
- APK URL HTTP 200 এবং size `896181 bytes` ফেরত দিচ্ছে।

## 2026-10-22 — Panel অনুযায়ী দুই slider-এর empty slide ও image loading fix

### অনুরোধ
- Admin panel দেখে Splash ও Onboarding slider ঠিক করা।

### সম্পন্ন ধাপ
1. Panel-এর খালি Splash placeholder row-গুলো config-এ publish হওয়া বন্ধ করা হয়েছে।
2. Splash image loader-এ bounds ও bitmap পড়ার জন্য আলাদা HTTP connection ব্যবহার করা হয়েছে।
3. Splash ও Onboarding-এর panel-driven page configuration একইভাবে নির্ভরযোগ্য রাখার ব্যবস্থা করা হয়েছে।

### পরিবর্তিত ফাইল
- `php-control-panel/public/admin.php` — খালি Splash page filter করা হয়েছে।
- `app/src/main/java/app/agriwealth/portal/MainActivity.kt` — Splash image connection loading fix।

### যাচাই
- `gradle :app:assembleRelease -Pandroid.aapt2FromMavenOverride=/tmp/agri-aapt2-dir/aapt2` সফল হয়েছে।
- Build output ও `SecureWebPortal-release-latest.apk` একই SHA-256: `230fe197f7e28ca7df20b71fd42e7e63d16c6752b09e95bfce84bfb6560fd90e`।
- PHP CLI এই runtime-এ available/executable না থাকায় `php -l` চালানো যায়নি।

### পরবর্তী করণীয়
- Device-এ panel থেকে এক ও একাধিক Splash/Onboarding page দিয়ে swipe ও auto-advance যাচাই করা।

## 2026-10-23 — Onboarding ও flash photo bounce animation

### অনুরোধ
- Onboarding এবং flash screen-এর photo-তে bounce animation যোগ করা।

### সম্পন্ন ধাপ
1. Onboarding-এর remote photo slide দৃশ্যমান হওয়ার সময় scale-and-settle bounce animation যোগ করা হয়েছে।
2. Flash screen-এর preload এবং background load—দুই পথেই photo bounce animation যোগ করা হয়েছে।
3. Hidden onboarding slide আগে থেকে bounce না করে slide active হওয়ার সময় animation চালানোর ব্যবস্থা করা হয়েছে।
4. Release APK rebuild করে shared latest APK copy আপডেট করা হয়েছে।

### পরিবর্তিত ফাইল
- `app/src/main/java/app/agriwealth/portal/OnboardingView.kt` — onboarding photo bounce এবং active-slide timing।
- `app/src/main/java/app/agriwealth/portal/MainActivity.kt` — flash photo bounce animation।
- `SecureWebPortal-release-latest.apk` — নতুন release build।

### যাচাই
- `gradle :app:assembleRelease -Pandroid.aapt2FromMavenOverride=/tmp/agri-aapt2-dir/aapt2` সফল হয়েছে।
- Build output ও shared APK-এর SHA-256 একই: `dd849e8939dcbce91d3f4ff76277f9e0539fda5c0f9140518a36ed7f17260bea`।

### পরবর্তী করণীয়
- Device-এ onboarding ও flash photo-এর bounce timing এবং visual scale হাতে যাচাই করা।

## 2026-10-24 — Stale admin package ঠিক করা

### অনুরোধ
- সর্বশেষ admin panel-এ push notification ও অন্য নতুন update দেখা যাচ্ছে না; panel/package পরীক্ষা করে ঠিক করা।

### কারণ শনাক্ত
1. `SecureWebPortal-admin-panel-latest.zip` ২০ অক্টোবরের পুরোনো archive ছিল; এতে বর্তমান push notification, welcome popup image এবং send action ছিল না।
2. বর্তমান source (`admin-panel-root`, `php-control-panel`, `admin-panel-root-update`) একই নতুন সংস্করণে আছে।
3. full package-এর storage config এবং live `remote-config` আলাদা; existing installation update-এর জন্য storage overwrite করা নিরাপদ নয়।

### সম্পন্ন ধাপ
1. পুরোনো দুইটি package `backups/stale-admin-packages-20261024/`-এ রেখে recoverable backup করা হয়েছে।
2. বর্তমান source থেকে `SecureWebPortal-admin-panel-latest.zip` full package পুনর্নির্মাণ করা হয়েছে।
3. বর্তমান source থেকে `SecureWebPortal-admin-panel-update.zip` code-only update package পুনর্নির্মাণ করা হয়েছে।
4. দুই package-এ push provider settings, welcome image এবং `send_notification` action উপস্থিত আছে তা archive content দিয়ে যাচাই করা হয়েছে।

### পরিবর্তিত ফাইল
- `SecureWebPortal-admin-panel-latest.zip` — বর্তমান full panel package।
- `SecureWebPortal-admin-panel-update.zip` — বর্তমান safe update package।
- `backups/stale-admin-packages-20261024/` — আগের package-এর backup।

### যাচাই
- Current source-এর তিনটি admin copy-এর SHA-256 একই।
- নতুন archive-এ `push_provider`, `send_notification`, `welcome_image` পাওয়া গেছে।
- Push ও Firebase/OneSignal code current panel source-এ উপস্থিত।
- PHP CLI executable না থাকায় `php -l` চালানো যায়নি; archive validation-এর জন্য `busybox unzip -l` এবং content inspection ব্যবহার করা হয়েছে।

### পরবর্তী করণীয়
- Existing hosting হলে শুধু `SecureWebPortal-admin-panel-update.zip` extract করে code overwrite করতে হবে; `private/` ও `storage/` অক্ষত রাখতে হবে।

## 2026-10-24 — Local download page-এ Admin update link যোগ

### অনুরোধ
- লোকাল সার্ভারের download page-এ নতুন Admin Panel package-এর link যোগ করা।

### সম্পন্ন ধাপ
1. Full Admin Panel package-এর link রাখা হয়েছে।
2. Existing cPanel Admin Panel-এর safe update package-এর link যোগ করা হয়েছে।
3. Root-layout Admin Panel update package-এর link যোগ করা হয়েছে।

### পরিবর্তিত ফাইল
- `index.html` — নতুন Admin package download links।
- `download.html` — একই download links।

### যাচাই
- তিনটি link-এর target file workspace-এ পাওয়া গেছে।

## 2026-10-24 — Low-data remote config version check

### অনুরোধ
- Admin Panel update হলে সর্বোচ্চ ৫ সেকেন্ডে app-এ ধরা পড়বে, কিন্তু config পরিবর্তন না হলে পুরো JSON download হবে না।
- HEAD/ETag-এর বদলে hosting-compatible ছোট endpoint ব্যবহার করা।

### সম্পন্ন ধাপ
1. Admin API-তে `portal-config.version` resource যোগ করা হয়েছে; এটি signed config-এর SHA-256 hash দেয়।
2. App-এর polling interval ৫ সেকেন্ড রাখা হয়েছে, কিন্তু polling-এ শুধু version hash GET করা হয়।
3. Version hash বদলালেই JSON ও signature download এবং RSA/SHA-256 verification হয়।
4. Cached verified config এবং APK-এর bundled config fallback হিসেবে রাখা হয়েছে।
5. Release APK rebuild করে local download copy আপডেট করা হয়েছে।
6. cPanel এবং root-layout Admin API source/package আপডেট করা হয়েছে।

### পরিবর্তিত ফাইল
- `app/src/main/java/app/agriwealth/portal/MainActivity.kt` — version endpoint polling ও config cache।
- `app/src/main/assets/portal-config.json` — bundled fallback config।
- `php-control-panel/public/api.php` — `portal-config.version` endpoint।
- `admin-panel-root/api.php` — একই endpoint।
- `admin-panel-root-update/api.php` — একই endpoint।
- `SecureWebPortal-release-latest.apk` — নতুন release APK।
- `SecureWebPortal-admin-panel-update.zip` — API update package।

### গুরুত্বপূর্ণ নোট
- `app/common.php`-এ এই কাজের জন্য কোনো code পরিবর্তন করা হয়নি।
- Live hosting-এ Admin API update install না করা পর্যন্ত `portal-config.version` endpoint কাজ করবে না।

### যাচাই
- `gradle :app:assembleRelease -Pandroid.aapt2FromMavenOverride=/tmp/agri-aapt2-dir/aapt2` সফল হয়েছে।
- নতুন APK SHA-256: `9a9572dc3ff7a72753d3bc13d223d897a15f24b986dd91712c82d1641e28ae49`।
- Live API পরীক্ষা করে দেখা হয়েছে যে নতুন endpoint এখনো deploy হয়নি এবং `404` দিচ্ছে।

### পরবর্তী করণীয়
- Hosting-এ `SecureWebPortal-admin-panel-update.zip` থেকে শুধু code update করতে হবে; `storage/` ও `private/` অক্ষত রাখতে হবে।

## 2026-10-24 — AI work history rules স্থায়ী করা

### অনুরোধ
- আগের সব কাজ ও history অক্ষত রেখে ভবিষ্যতে যেকোনো AI-এর কাজের history নিয়ম মেনে রাখা।

### সম্পন্ন ধাপ
1. Root-level `AGENTS.md` যোগ করা হয়েছে।
2. কাজ শুরুর আগে project context ও worklog পড়া বাধ্যতামূলক করা হয়েছে।
3. কাজ শেষে changed files, verification, hashes এবং deployment notes সহ worklog entry লেখার নিয়ম যোগ করা হয়েছে।
4. পুরোনো history মুছা বা rewrite না করার নিয়ম যোগ করা হয়েছে।

### পরিবর্তিত ফাইল
- `AGENTS.md` — ভবিষ্যৎ AI agent-এর project work rules।
- `chat-history/WORKLOG.md` — এই স্থায়ী নিয়ম যোগ করার record।

### যাচাই
- আগের source, config, package এবং history অপরিবর্তিত রাখা হয়েছে।

## 2026-10-24 — Live Admin API deployment যাচাই

### অনুরোধ
- Hosting-এ Admin Panel update করার পর app-এর remote config control ঠিকমতো কাজ করছে কি না পরীক্ষা করা।

### যাচাই
- Live `portal-config.version` endpoint HTTP 200 দিয়েছে এবং 64-character hash ফেরত দিয়েছে।
- Live config-এর SHA-256 hash version response-এর সঙ্গে মিলে গেছে।
- Live `portal-config.json` ও `.sig` দুটিই HTTP 200 দিয়েছে।
- Live signature local public key দিয়ে `openssl dgst -sha256 -verify`-এ `Verified OK` হয়েছে।
- Live config-এ app name, start URL, splash, onboarding, maintenance, security এবং popup/push sections পাওয়া গেছে।
- Live endpoint-এ ETag-ও আছে, যদিও নতুন app version check-এর জন্য ছোট version endpoint ব্যবহার করে।

### ফলাফল
- Admin API deployment server-side সঠিকভাবে কাজ করছে।
- Admin Panel থেকে config save করলে signed config-এর version বদলাবে; নতুন APK polling করে পরিবর্তন ধরতে পারবে।

### সীমাবদ্ধতা
- Device থেকে প্রতিটি UI control পরিবর্তন করে end-to-end পরীক্ষা করা হয়নি; এতে live config বদলানো লাগত।

## 2026-10-24 — Push integrationসহ release APK rebuild

### সম্পন্ন ধাপ
1. বর্তমান Firebase push integration ও version polling codeসহ release APK rebuild করা হয়েছে।
2. `SecureWebPortal-release-latest.apk` এবং `local-downloads/SecureWebPortal-release-latest.apk` আপডেট করা হয়েছে।
3. Local HTTP server থেকে APK response পরীক্ষা করা হয়েছে।

### যাচাই
- Gradle release build সফল হয়েছে।
- APK ও local download copy-এর SHA-256 একই: `9a9572dc3ff7a72753d3bc13d223d897a15f24b986dd91712c82d1641e28ae49`।
- Local APK URL HTTP 200 দিয়েছে।

## 2026-10-24 — Push notification integration যাচাই

### যাচাই
- Live config-এ push enabled এবং provider `firebase` পাওয়া গেছে।
- Live Firebase project ID, application ID, API key, sender ID, channel ID এবং permission request settings পাওয়া গেছে।
- Admin Panel-এ Firebase send action, topic `all_users`, service-account credential lookup এবং image/click URL validation উপস্থিত আছে।
- Android app-এ dynamic Firebase initialization, `all_users` topic subscription, Android 13 notification permission এবং FCM message receiver উপস্থিত আছে।
- Notification-এর HTTPS image ও HTTPS click URL handling code যাচাই করা হয়েছে।

### ফলাফল
- Admin ও app-এর push integration configuration অনুযায়ী প্রস্তুত।

### সীমাবদ্ধতা
- Private Firebase service account এবং physical device notification delivery এই environment থেকে সরাসরি পরীক্ষা করা হয়নি। Admin Panel থেকে test notification পাঠিয়ে result `Firebase sent: ...` এবং device notification দেখা গেলে end-to-end কাজ নিশ্চিত হবে।
## 2026-10-24 — Push notification tap behavior যাচাই

### অনুরোধ
- Push notification চাপলে app open হবে কি না নিশ্চিত করা।

### কারণ বা সমস্যা শনাক্তকরণ
- Android notification service-এর `PendingIntent` যাচাই করে tap behavior নির্ধারণ করা হয়েছে।

### সম্পন্ন ধাপ
1. `PortalFirebaseMessagingService.kt`-এর notification click handling পড়া হয়েছে।
2. HTTPS `click_url` থাকলে `ACTION_VIEW` দিয়ে browser খোলে এবং click URL না থাকলে `MainActivity` খোলে—এটি নিশ্চিত করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- শুধু `chat-history/WORKLOG.md`-এ এই যাচাইয়ের record যোগ করা হয়েছে; source বা package পরিবর্তন করা হয়নি।

### যাচাই
- `app/src/main/java/app/agriwealth/portal/PortalFirebaseMessagingService.kt` এবং Android manifest পর্যালোচনা করা হয়েছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- Admin Panel-এর notification form-এ Click URL খালি রাখলে notification tap করলে app খুলবে। HTTPS Click URL দিলে browser খুলবে।

## 2026-10-24 — নতুন signing key ও package-এর জন্য Admin Panel ব্যবহারের নির্দেশনা

### অনুরোধ বা উদ্দেশ্য
- নতুন signing key, package name, admin panel name এবং আলাদা config দিয়ে আলাদা project তৈরি করলে প্রয়োজনীয় Admin Panel ফাইল কীভাবে নেওয়া হবে তা ব্যাখ্যা করা।

### কারণ বা সমস্যা শনাক্তকরণ
- `php-control-panel/` মূল installation package এবং `admin-panel-root/` document-root deployment-এর জন্য প্রস্তুত copy। নতুন app-এর public signing key পুরোনো panel-এর key থেকে আলাদা হলে পুরোনো signed config সরাসরি ব্যবহার করা যাবে না।

### সম্পন্ন ধাপ
1. README, cPanel/root deployment instructions, signing workflow এবং app package configuration পর্যালোচনা করা হয়েছে।
2. নতুন project-এর জন্য code-only panel files নেওয়া এবং নতুন `private/` ও `storage/` তৈরি করার নির্দেশনা নির্ধারণ করা হয়েছে।
3. নতুন app-এর embedded public key ও Admin Panel-এর signing key pair একই হতে হবে—এই compatibility rule নথিভুক্ত করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- শুধু `chat-history/WORKLOG.md` পরিবর্তিত হয়েছে; source বা deployment package পরিবর্তন করা হয়নি।

### যাচাই
- `php-control-panel/README.md`, `CPANEL-SETUP-BN.txt`, `admin-panel-root/ROOT-DEPLOY-BN.txt`, `app/common.php` এবং `README.md` পর্যালোচনা করা হয়েছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- নতুন project-এর জন্য `php-control-panel/`-এর fresh installation ব্যবহার করতে হবে; পুরোনো `private/` ও `storage/` কপি করা যাবে না। নতুন app build-এ নতুন panel API URL দিতে হবে।

## 2026-10-24 — নতুন project-এর প্রয়োজনীয় key, config ও panel তৈরির পদ্ধতি

### অনুরোধ বা উদ্দেশ্য
- নতুন package name, signing key, app name ও আলাদা Admin Panel-এর জন্য প্রয়োজনীয় উপকরণ তৈরির ধাপ ব্যাখ্যা করা।

### সম্পন্ন ধাপ
1. Android release keystore এবং remote-config RSA signing key আলাদা করে চিহ্নিত করা হয়েছে।
2. RSA private/public key, Android public-key resource, initial JSON/signature, Admin Panel installation এবং API URL-এর workflow সাজানো হয়েছে।
3. PHP installer ও existing signing script অনুযায়ী command examples প্রস্তুত করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- শুধু `chat-history/WORKLOG.md` পরিবর্তিত হয়েছে; source, package বা deployment files পরিবর্তিত হয়নি।

### যাচাই
- `php-control-panel/README-BN.md`, `CPANEL-SETUP-BN.txt`, `tools/sign-config.sh`, `app/build.gradle.kts`, `app/src/main/res/values/strings.xml` এবং `remote-config/portal-config.json` পর্যালোচনা করা হয়েছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- নিচের নির্দেশনা অনুসারে নতুন key, config ও fresh Admin Panel তৈরি করে API URL দিয়ে নতুন APK build করতে হবে।

## 2026-10-24 — নতুন project setup documentation

### অনুরোধ বা উদ্দেশ্য
- নতুন package name, key, config এবং Admin Panel তৈরি করার জন্য ধাপে ধাপে ব্যবহারযোগ্য বাংলা document তৈরি করা এবং প্রয়োজনীয় project documentation update করা।

### কারণ বা সমস্যা শনাক্তকরণ
- Android release keystore এবং remote-config signing RSA key আলাদা; fresh Admin Panel deployment-এ পুরোনো `private/` ও `storage/` পুনঃব্যবহার করা যাবে না। এই workflow এক জায়গায় documented ছিল না।

### সম্পন্ন ধাপ
1. Package rename, Android release keystore, config signing key এবং Base64 public key তৈরির ধাপ যোগ করা হয়েছে।
2. Initial JSON, signing, Admin Panel fresh installation, cPanel/root deployment, Firebase setup এবং API URL দিয়ে APK build-এর ধাপ যোগ করা হয়েছে।
3. API/signature verification এবং final deployment checklist যোগ করা হয়েছে।
4. Root README ও project context-এ নতুন document-এর link যোগ করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- `NEW-PROJECT-SETUP-BN.md` — নতুন পূর্ণ setup guide।
- `README.md` — setup guide-এর link।
- `chat-history/PROJECT-CONTEXT.md` — নতুন guide-এর স্থায়ী reference।
- `chat-history/WORKLOG.md` — এই entry।

### যাচাই
- নতুন document-এর commands, file paths, Admin Panel structure এবং existing installer/signing workflow-এর সঙ্গে মিলিয়ে পড়া হয়েছে।
- Documentation-only পরিবর্তন হওয়ায় Gradle build বা automated test চালানো হয়নি।

### Live deployment বা user-এর পরবর্তী করণীয়
- [`NEW-PROJECT-SETUP-BN.md`](../NEW-PROJECT-SETUP-BN.md) অনুসরণ করে নতুন project তৈরি করতে হবে। Live hosting বা external service-এ কোনো deployment করা হয়নি।

## 2026-10-24 — হাতে-কলমে নতুন project setup runbook

### অনুরোধ বা উদ্দেশ্য
- আগের setup document-এর পাশাপাশি প্রতিটি ধাপে বাস্তব কাজ, command, expected output এবং verification checkpoint যোগ করা।

### কারণ বা সমস্যা শনাক্তকরণ
- শুধু overview যথেষ্ট নয়; নতুন project তৈরির সময় package, দুই ধরনের key, config signature, fresh Admin Panel, hosting এবং APK build-এর প্রতিটি ধাপে আলাদা যাচাই দরকার।

### সম্পন্ন ধাপ
1. `NEW-PROJECT-HANDS-ON-BN.md` তৈরি করা হয়েছে।
2. Software check, source copy, package rename, Android keystore, config RSA key, public key conversion, JSON creation/signing, fresh panel creation, local panel test, hosting deployment, APK build এবং Firebase test ধাপে ভাগ করা হয়েছে।
3. প্রতিটি গুরুত্বপূর্ণ ধাপে expected result এবং failure checkpoint যোগ করা হয়েছে।
4. মূল setup guide, root README এবং project context-এ hands-on guide-এর link যোগ করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- `NEW-PROJECT-HANDS-ON-BN.md` — command-by-command runbook।
- `NEW-PROJECT-SETUP-BN.md` — hands-on guide-এর link।
- `README.md` — hands-on guide-এর link।
- `chat-history/PROJECT-CONTEXT.md` — নতুন guide-এর reference।
- `chat-history/WORKLOG.md` — এই entry।

### যাচাই
- Document-এর 507 lines, command references, file paths, `Verified OK` checks, API checks এবং Gradle build references পর্যালোচনা করা হয়েছে।
- Documentation-only কাজ হওয়ায় key তৈরি, hosting deployment বা APK build এই turn-এ চালানো হয়নি।

### Live deployment বা user-এর পরবর্তী করণীয়
- [`NEW-PROJECT-HANDS-ON-BN.md`](../NEW-PROJECT-HANDS-ON-BN.md) থেকে ধাপ ১ শুরু করে হাতে-কলমে কাজ করতে হবে। Live deployment করা হয়নি।

## 2026-10-24 — Documentation maintenance rule স্থায়ী করা

### অনুরোধ বা উদ্দেশ্য
- ভবিষ্যতে source বা deployment workflow বদলালে setup documents যেন update করা হয়, সেই নিয়ম স্থায়ীভাবে সংরক্ষণ করা।

### সম্পন্ন ধাপ
1. `AGENTS.md`-এ source, package, signing, Admin Panel, Firebase বা deployment পরিবর্তনের সঙ্গে documentation update বাধ্যতামূলক করা হয়েছে।
2. `PROJECT-CONTEXT.md`-এ দুইটি setup guide-কে স্থায়ী reference এবং update rule হিসেবে যোগ করা হয়েছে।
3. দুই setup guide-এ source পরিবর্তনের সঙ্গে command, path, expected output এবং worklog update করার নিয়ম লেখা হয়েছে।

### পরিবর্তিত ফাইল
- `AGENTS.md`
- `chat-history/PROJECT-CONTEXT.md`
- `NEW-PROJECT-SETUP-BN.md`
- `NEW-PROJECT-HANDS-ON-BN.md`
- `chat-history/WORKLOG.md`

### যাচাই
- Maintenance rules ও guide references পড়ে নিশ্চিত করা হয়েছে যে future implementation/deployment change-এর সময় document update এবং worklog record দুটোই প্রয়োজন হবে।

### Live deployment বা user-এর পরবর্তী করণীয়
- ভবিষ্যতের প্রতিটি সংশ্লিষ্ট কাজের সময় এই maintenance rule অনুসরণ করা হবে। কোনো live deployment করা হয়নি।


## 2026-10-26 — সর্বশেষ Admin ও Android source ZIP delivery

### অনুরোধ বা উদ্দেশ্য
- সর্বশেষ Admin Panel ও Android app code ZIP আকারে দেওয়া।

### কারণ বা সমস্যা শনাক্তকরণ
- আগের app source ZIP-এ `MainActivity.kt` বর্তমান source-এর চেয়ে পুরোনো ছিল।
- আগের Admin root ZIP-এ `private/` signing key এবং `storage/` live config/state ছিল; code delivery-তে এগুলো থাকা উচিত নয়।

### সম্পন্ন ধাপ
1. `app-source-package/`-এর `MainActivity.kt` বর্তমান `app/` source-এর সঙ্গে sync করা হয়েছে।
2. বর্তমান app source/build files দিয়ে Android source ZIP তৈরি করা হয়েছে।
3. Admin root deployment code ZIP তৈরি করা হয়েছে; এতে installer ও setup document আছে, `private/` এবং `storage/` নেই।
4. দুই ZIP `local-downloads/`-এও রাখা হয়েছে এবং README ও দুই setup guide-এ archive reference যোগ করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- `SecureWebPortal-app-source-latest-20261026.zip` এবং `local-downloads/SecureWebPortal-app-source-latest-20261026.zip`
- `SecureWebPortal-admin-code-latest-20261026.zip` এবং `local-downloads/SecureWebPortal-admin-code-latest-20261026.zip`
- `app-source-package/app/src/main/java/app/agriwealth/portal/MainActivity.kt`
- `README.md`, `NEW-PROJECT-SETUP-BN.md`, `NEW-PROJECT-HANDS-ON-BN.md`, `chat-history/WORKLOG.md`

### যাচাই
- দুই ZIP-এর `testzip()` সফল; app ZIP-এ বর্তমান `MainActivity.kt`-এর SHA-256 মিলেছে।
- Admin ZIP-এ `admin.php`, `api.php` ও installer আছে; `private/` বা `storage/` entry নেই।
- Build বা live deployment চালানো হয়নি; শুধু code archive যাচাই করা হয়েছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- ব্যবহারযোগ্য ফাইল: উপরের দুই `*-latest-20261026.zip`; একই ফাইল `local-downloads/`-এও আছে।
- Admin ZIP fresh root deployment template। Hosting-এ ব্যবহার করলে installer চালিয়ে নতুন key/config তৈরি করতে হবে; live deployment করা হয়নি।

## 2026-10-26 — একই configuration-এ Android update APK

### অনুরোধ বা উদ্দেশ্য
- বর্তমান Secure Web Portal app configuration বজায় রেখে update APK তৈরি করা।

### কারণ বা সমস্যা শনাক্তকরণ
- `app/build.gradle.kts`-এ পূর্বের `versionCode = 1` থাকায় Android update-এর জন্য নতুন version প্রকাশ করা প্রয়োজন ছিল। Package ID, remote config URL, bundled config এবং release signing setup একই রাখতে হবে।

### সম্পন্ন ধাপ
1. Android app `versionCode` 2 এবং `versionName` 1.0.1 করা হয়েছে; package ID, API URL, bundled portal config ও signing properties অপরিবর্তিত।
2. একই configured release keystore দিয়ে `:app:assembleRelease` force rebuild করা হয়েছে।
3. APK-টি root, `local-downloads/` ও `delivery/`-এর প্রতিষ্ঠিত download paths-এ কপি করা হয়েছে।
4. App source ZIP-গুলোও নতুন version metadata-সহ পুনরায় তৈরি করা হয়েছে।
5. দুই setup guide-এ পরবর্তী APK update-এর versionCode/versionName increment নিয়ম যোগ করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- APK: `SecureWebPortal-release-latest.apk`, `SecureWebPortal-release.apk`, `local-downloads/SecureWebPortal-release-latest.apk`, `local-downloads/SecureWebPortal-release.apk`, `delivery/SecureWebPortal-release.apk`
- App source ZIP: `SecureWebPortal-apk-source-latest.zip`, `SecureWebPortal-app-source-latest-20261026.zip` এবং `local-downloads/`-এর কপি
- `app/build.gradle.kts`, `app-source-package/app/build.gradle.kts`
- `NEW-PROJECT-SETUP-BN.md`, `NEW-PROJECT-HANDS-ON-BN.md`, `chat-history/WORKLOG.md`

### যাচাই
- `gradle :app:assembleRelease --rerun-tasks -Pandroid.aapt2FromMavenOverride=/tmp/aapt2-wrapper/aapt2` — `BUILD SUCCESSFUL`।
- Gradle output metadata-তে versionCode 2/versionName 1.0.1; APK ZIP structure-এ Android manifest ও signing certificate block আছে।
- APK download copy-গুলোর SHA-256 build output-এর সঙ্গে মিলে; source ZIP integrity ও Gradle version metadata মিলেছে।
- নতুন APK-এর signing certificate fingerprint আগের APK (`delivery/SecureWebPortal-release-previous.apk`)-এর সঙ্গে OpenSSL দিয়ে মেলানো হয়েছে; একই certificate নিশ্চিত। APK-এর পূর্ণ external `apksigner` verify এই environment-এর JVM memory execution restriction-এ চলেনি; Android Gradle signing validation/build সফল হয়েছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- Install/update file: `SecureWebPortal-release-latest.apk` অথবা `local-downloads/SecureWebPortal-release-latest.apk`।
- Live Admin Panel/remote hosting-এ কোনো পরিবর্তন বা deployment করা হয়নি।

## 2026-10-26 — পূর্ণ Admin Control ও Android App Code ZIP delivery

### অনুরোধ বা উদ্দেশ্য
- পূর্ণ Admin Control ZIP এবং বর্তমান Android app code ZIP তৈরি করে local server-এর download link দেওয়া।

### কারণ বা সমস্যা শনাক্তকরণ
- আগের app source archive-এ build cache ছিল এবং bundled app asset বাদ পড়েছিল।
- Admin Panel-এর cPanel/shared-hosting ও direct root deployment layout আলাদা; পূর্ণ package-এ current config state এবং signing key আছে, তাই এটি local/private delivery হিসেবে চিহ্নিত করা দরকার।

### সম্পন্ন ধাপ
1. বর্তমান `php-control-panel/` থেকে পূর্ণ cPanel/shared-hosting Admin package archive তৈরি করা হয়েছে।
2. বর্তমান `admin-panel-root/` থেকে পূর্ণ direct document-root Admin package archive তৈরি করা হয়েছে।
3. বর্তমান root `app/`, Gradle files, bundled config, `remote-config/`, signing script এবং setup documents দিয়ে clean Android app code archive তৈরি করা হয়েছে; build cache ও signing keys বাদ রাখা হয়েছে।
4. root README, দুই setup guide এবং local download pages-এ নতুন archive links যোগ করা হয়েছে।
5. সব archive `local-downloads/`-এ কপি করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- `SecureWebPortal-admin-control-full-20261026.zip`
- `SecureWebPortal-admin-control-root-full-20261026.zip`
- `SecureWebPortal-app-code-full-20261026.zip`
- একই তিনটি archive-এর `local-downloads/` copy
- `README.md`, `NEW-PROJECT-SETUP-BN.md`, `NEW-PROJECT-HANDS-ON-BN.md`, `index.html`, `download.html`

### যাচাই
- তিনটি ZIP-এর `testzip()` সফল হয়েছে।
- App code ZIP-এ 54টি file আছে; build cache নেই, bundled `app/src/main/assets/portal-config.json` আছে, private signing key নেই।
- Full cPanel Admin ZIP-এ 25টি file এবং root Admin ZIP-এ 24টি file আছে; private key ও storage state ইচ্ছাকৃতভাবে included, কারণ এগুলো “full” local delivery package।
- ZIP SHA-256: Admin cPanel `e606a1f099c96d73d89516492dc02afed48fd3d7ed6efbb74834735969006a17`; Admin root `da095e576fca567aed830446af3870c0c0bbf2be0fe1957ad461b0fb3e8a8c6a`; App code `bc4e56bc02e140dc43808a1de1260dd27dc8436fda53a7c171add8b661c921e7`।
- Build বা live hosting deployment চালানো হয়নি; archive integrity এবং source contents যাচাই করা হয়েছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- Local server চালু হলে download page এবং তিনটি direct link ব্যবহার করা যাবে। Full Admin ZIP কেবল নিরাপদ local server-এ রাখবেন; public hosting-এ upload করবেন না। Live deployment করা হয়নি।

## 2026-10-26 — Admin Panel local server চালু

### অনুরোধ বা উদ্দেশ্য
- Admin Panel local server-এ রান করে browser login ও signed config API যাচাই করা।

### কারণ বা সমস্যা শনাক্তকরণ
- `php-control-panel/`-এ `private/config.php` না থাকায় panel আগে installed ছিল না। Local development-এর জন্য CLI installer চালিয়ে credentials ও signing configuration তৈরি করা প্রয়োজন ছিল।

### সম্পন্ন ধাপ
1. PHP 8.5.12, OpenSSL, fileinfo, curl, json ও session extension যাচাই করা হয়েছে।
2. Local admin user `localadmin` দিয়ে installer চালানো হয়েছে; password worklog-এ লেখা হয়নি।
3. `private/config.php` তৈরি হয়েছে এবং signing key/storage permissions installer অনুযায়ী প্রয়োগ হয়েছে।
4. `AGRI_ALLOW_HTTP_ADMIN=1 php -S 127.0.0.1:8080 -t public` দিয়ে local server চালু করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- `php-control-panel/private/config.php` — local-only hashed admin credentials ও signing key path।
- `php-control-panel/storage/` ও permission metadata installer অনুযায়ী যাচাই হয়েছে।
- PHP source বা package code পরিবর্তন করা হয়নি।

### যাচাই
- `http://127.0.0.1:8080/admin.php` HTTP 200 login page দিয়েছে।
- `api.php?file=portal-config.json` এবং `.sig` endpoint HTTP 200 দিয়েছে।
- `admin.php`, `api.php` ও `app/common.php` PHP syntax check সফল।
- `private/config.php` mode 0600, private signing key mode 0400, storage mode 0750 এবং config/signature mode 0640 নিশ্চিত করা হয়েছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- Browser-এ [`http://127.0.0.1:8080/admin.php`](http://127.0.0.1:8080/admin.php) খুলুন। Username: `localadmin`; local password এই কাজের সময় সেট করা হয়েছে।
- এটি local development server; live hosting deployment করা হয়নি।

## 2026-10-26 — শেষ Android release ও local Admin Panel compatibility যাচাই

### অনুরোধ বা উদ্দেশ্য
- শেষ করা Android release app-এর সঙ্গে বর্তমান Admin Panel code ও signing configuration সঠিকভাবে কাজ করে কি না যাচাই করা।

### কারণ বা সমস্যা শনাক্তকরণ
- Local Admin Panel `127.0.0.1:8080`-এ চলছে, কিন্তু শেষ APK-এর compiled `remote_config_url` এখনো `http://104.243.34.108/~ssvilfyk/api.php?file=portal-config.json`। তাই code/key compatibility এবং local endpoint connectivity আলাদা করে যাচাই করা প্রয়োজন।

### সম্পন্ন ধাপ
1. App-এর embedded RSA public key এবং `php-control-panel/storage/config-signing-public.pem` DER bytes তুলনা করা হয়েছে।
2. Local Admin API থেকে পাওয়া JSON ও Base64 signature download করা হয়েছে।
3. একই public key দিয়ে local JSON signature `openssl dgst -sha256 -verify` দ্বারা যাচাই করা হয়েছে।
4. শেষ APK-এর resource metadata থেকে compiled remote URL এবং signing certificate block যাচাই করা হয়েছে।
5. Android manifest-এ local HTTP testing-এর জন্য `usesCleartextTraffic="true"` উপস্থিত আছে নিশ্চিত করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- Source বা package code পরিবর্তন করা হয়নি; শুধু `chat-history/WORKLOG.md`-এ verification record যোগ করা হয়েছে।

### যাচাই
- App embedded key SHA-256 এবং Admin public key SHA-256 একই: `d5e4b4e2bff490250c7ab6185a8c8140398ca7bd16f0eb00c9c3bbaa898281a9`।
- Local Admin JSON/signature pair `Verified OK`।
- Admin API endpoints HTTP 200 দিয়েছে এবং config schema app parser-এর fields-এর সঙ্গে সামঞ্জস্যপূর্ণ।
- শেষ APK-তে Android manifest ও release signature block আছে।

### ফলাফল ও Live deployment বা user-এর পরবর্তী করণীয়
- Admin Panel code, JSON schema এবং signing key শেষ Android app-এর সঙ্গে compatible। Admin Panel থেকে save করা signed config app গ্রহণ করতে পারবে, যদি app সেই Admin API URL-এ build করা থাকে।
- বর্তমান শেষ APK local panel-এ connect করবে না, কারণ তার endpoint remote IP-তে compiled। Local panel ব্যবহার করে নতুন APK build করতে হবে: emulator-এর জন্য `-PremoteConfigUrl='http://10.0.2.2:8080/api.php?file=portal-config.json'`, physical device-এর জন্য computer-এর LAN IP ব্যবহার করুন।
- Live hosting deployment করা হয়নি।

## 2026-10-26 — Admin Panel-এর configured feature compatibility review

### অনুরোধ বা উদ্দেশ্য
- Admin Panel থেকে নিয়ন্ত্রিত সব feature শেষ Android app-এ কাজ করবে কি না যাচাই করা।

### কারণ বা সমস্যা শনাক্তকরণ
- Config schema-তে কিছু security field আছে যেগুলো current Android implementation শুধু preserve করে, প্রয়োগ করে না। Push provider-এর জন্য external credential ও device delivery আলাদা dependency।

### সম্পন্ন ধাপ
1. Admin form-এর save fields এবং Android `PortalConfig` parser মিলিয়ে দেখা হয়েছে।
2. Splash, onboarding, maintenance, UI colors, welcome popup, allowed domains, external links, selection, screenshot, root/emulator/debugger policy এবং polling flow যাচাই করা হয়েছে।
3. Firebase/OneSignal initialization, topic subscription, notification click handling এবং image URL restrictions পর্যালোচনা করা হয়েছে।
4. `ssl_pinning` ও `certificate_pins` field-এর implementation usage খোঁজা হয়েছে।

### পরিবর্তিত ফাইল ও package
- Source/package code পরিবর্তন করা হয়নি; শুধু এই verification record যোগ করা হয়েছে।

### যাচাই
- Current configured feature set-এর Admin field এবং Android handler/parser সামঞ্জস্যপূর্ণ।
- `ssl_pinning` ও `certificate_pins` সংরক্ষিত হয়, কিন্তু current WebView build-এ enforce হয় না।
- Push code আছে; credentials, provider account এবং physical device test ছাড়া delivery guarantee করা যায় না।

### Live deployment বা user-এর পরবর্তী করণীয়
- শেষ APK-কে এই Admin Panel-এর API URL দিয়ে build করতে হবে; তারপর Admin save করলে foreground app সাধারণত কয়েক সেকেন্ডের মধ্যে signed config নেয়।
- Push চালাতে valid Firebase/OneSignal setup ও device permission লাগবে। SSL pinning চাইলে Android implementation আলাদা করে যোগ করতে হবে। Live deployment করা হয়নি।

## 2026-10-26 — Android Studio APK rebuild-এর Admin ও app পরিবর্তন নির্দেশনা

### অনুরোধ বা উদ্দেশ্য
- Live Admin Panel-এর সঙ্গে নতুন Android Studio APK তৈরি ও চালানোর জন্য কোন configuration পরিবর্তন করতে হবে তা নির্ধারণ করা।

### সম্পন্ন ধাপ
1. App-এর `remoteConfigUrl` Gradle property, embedded public key, package ID এবং release signing setup চিহ্নিত করা হয়েছে।
2. Admin Panel-এর public API URL, matching signing key, HTTPS requirement, config save/polling flow এবং push credential separation চিহ্নিত করা হয়েছে।
3. Emulator ও physical device-এর জন্য আলাদা local/live endpoint guidance নির্ধারণ করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- Source/package code পরিবর্তন করা হয়নি; শুধু rebuild নির্দেশনা record করা হয়েছে।

### যাচাই
- বর্তমান app package `app.agriwealth.portal`, versionCode 2/versionName 1.0.1 এবং `app/build.gradle.kts`-এর `remoteConfigUrl` property review করা হয়েছে।
- Current app public key ও Admin signing public key matching rule আগের verification-এর সঙ্গে consistent।

### Live deployment বা user-এর পরবর্তী করণীয়
- Live Admin Panel HTTPS URL বসিয়ে release APK rebuild করতে হবে; existing installed app update হলে একই release keystore ব্যবহার করতে হবে।
- Admin Panel ও app-এর configuration change বাস্তবে user-এর hosting/domain অনুযায়ী সম্পন্ন করতে হবে।

## 2026-10-26 — আলাদা App ও Admin Panel তৈরির বাংলা step-by-step document

### অনুরোধ বা উদ্দেশ্য
- নতুন Android App ও Admin Panel সম্পূর্ণ আলাদা করে configure, sign, deploy এবং build করার হাতে-কলমে বাংলা document তৈরি করা।

### সম্পন্ন ধাপ
1. নতুন project folder, package ID, Android release keystore এবং config signing RSA key-এর আলাদা workflow লিখে দেওয়া হয়েছে।
2. Public key Android resource-এ বসানো, JSON sign/verify, fresh Admin Panel structure, HTTPS deployment এবং API check যুক্ত করা হয়েছে।
3. Firebase/OneSignal separation, Android Studio release build, version increment এবং final verification checklist যোগ করা হয়েছে।
4. Root README ও project context-এ নতুন guide-এর reference যোগ করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- `NEW-SEPARATE-PROJECT-HANDS-ON-BN.md`
- `README.md`
- `chat-history/PROJECT-CONTEXT.md`
- `chat-history/WORKLOG.md`

### যাচাই
- Document-এর commands current `app/build.gradle.kts`, `tools/sign-config.sh`, Admin installer, setup structure এবং existing key/signature workflow-এর সঙ্গে মিলিয়ে লেখা হয়েছে।
- Source বা package code পরিবর্তন করা হয়নি।

### Live deployment বা user-এর পরবর্তী করণীয়
- [`NEW-SEPARATE-PROJECT-HANDS-ON-BN.md`](../NEW-SEPARATE-PROJECT-HANDS-ON-BN.md) অনুসরণ করে নতুন project তৈরি করতে হবে। Live deployment করা হয়নি।

## 2026-10-26 — Setup document-এর local download link ঠিক করা

### অনুরোধ বা উদ্দেশ্য
- নতুন separate project setup document browser থেকে download না হওয়ার সমস্যা ঠিক করা।

### সম্পন্ন ধাপ
1. Document-এর read permission 0644 করা হয়েছে।
2. Document-এর copy `local-downloads/`-এ রাখা হয়েছে।
3. `index.html` ও `download.html`-এ direct download link যোগ করা হয়েছে।

### যাচাই
- `http://127.0.0.1:8000/local-downloads/NEW-SEPARATE-PROJECT-HANDS-ON-BN.md` HTTP 200 এবং `text/markdown` response দিচ্ছে।
- Upload/download server চালু আছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- Browser-এ `127.0.0.1` link ব্যবহার করুন; live deployment করা হয়নি।

## 2026-10-26 — Separate project setup guide PDF

### অনুরোধ বা উদ্দেশ্য
- বাংলা separate App/Admin Panel setup document-এর PDF version তৈরি করে local server download link দেওয়া।

### সম্পন্ন ধাপ
1. `NEW-SEPARATE-PROJECT-HANDS-ON-BN.md` থেকে Bengali font support-সহ A4 PDF তৈরি করা হয়েছে।
2. PDF root এবং `local-downloads/`-এ রাখা হয়েছে।
3. `index.html`, `download.html` ও README-তে PDF link যোগ করা হয়েছে।

### পরিবর্তিত ফাইল ও package
- `NEW-SEPARATE-PROJECT-HANDS-ON-BN.pdf`
- `local-downloads/NEW-SEPARATE-PROJECT-HANDS-ON-BN.pdf`
- `index.html`, `download.html`, `README.md`

### যাচাই
- PDF file type ও page metadata valid।
- PDF size 50,617 bytes; SHA-256 `51ff68dc7f4e6a02c6e4b083565184c15676cd35b1454a7708841fccc52afc5f`।
- Local server থেকে PDF HTTP 200, `application/pdf` এবং `%PDF-` header দিয়েছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- [PDF download](http://127.0.0.1:8000/local-downloads/NEW-SEPARATE-PROJECT-HANDS-ON-BN.pdf) ব্যবহার করুন। Live deployment করা হয়নি।

## 2026-10-26 — সম্পূর্ণ agri workspace snapshot ZIP

### অনুরোধ বা উদ্দেশ্য
- `/workspace/agri`-এর বর্তমান সব file ও folder একই অবস্থায় একটি পূর্ণ backup ZIP তৈরি করা।

### সম্পন্ন ধাপ
1. Source, build output, Admin Panel, local config, keys, uploads, history এবং আগের archive-সহ সম্পূর্ণ workspace snapshot তৈরি করা হয়েছে।
2. Archive project folder-এর বাইরে তৈরি করে পরে `local-downloads/`-এ copy করা হয়েছে, যাতে archive নিজেকে recursiveভাবে অন্তর্ভুক্ত না করে।

### পরিবর্তিত ফাইল ও package
- `/workspace/agri-full-workspace-20261026.zip`
- `local-downloads/agri-full-workspace-20261026.zip`

### যাচাই
- ZIP file count: 3,134।
- `testzip()` সফল।
- Archive size: 168,414,466 bytes।
- SHA-256: `cf8f58ab024f753b0e2355581f58e7e2e94d4f798160a4b75e4665873b49d65e`।
- Local HTTP download response HTTP 200 এবং ZIP magic bytes `PK` দিয়েছে।

### Live deployment বা user-এর পরবর্তী করণীয়
- এটি সম্পূর্ণ private backup; এতে signing key, credentials state, config এবং build artifacts থাকতে পারে। Public hosting বা অন্য কারও কাছে upload করবেন না। Live deployment করা হয়নি।


## 2026-11-05 — সম্পূর্ণ agri workspace আপডেট snapshot ZIP

### অনুরোধ বা উদ্দেশ্য
- `/workspace/agri`-এর যাবতীয় সোর্স ও ফাইলসহ নতুন একটি সম্পূর্ণ জিপ ফাইল তৈরি করা।

### সম্পন্ন ধাপ
1. `/workspace/agri`-এর সব ফাইল নিয়ে একটি পূর্ণাঙ্গ ZIP ফাইল তৈরি করা হয়েছে: `/workspace/agri-full-workspace-20261105.zip` (এবং `agri-full-workspace-latest.zip`)।
2. লোকাল ডাউনলোড সার্ভার (`http://localhost:8000`) চালু করা হয়েছে এবং `download.html` ও `index.html`-এ সরাসরি ডাউনলোড লিংক যুক্ত করা হয়েছে।

### তৈরি হওয়া ফাইল
- `/workspace/agri-full-workspace-20261105.zip` (165.8 MB, 1856 files)
- `/workspace/agri/agri-full-workspace-latest.zip`
- `/workspace/agri/local-downloads/agri-full-workspace-latest.zip`

### যাচাই
- `testzip()` সফল (ZIP ভ্যালিড)।
- Local HTTP download response: HTTP 200 OK।
