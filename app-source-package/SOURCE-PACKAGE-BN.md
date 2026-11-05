APK source package
==================

এটি Android app-এর source code package। Build cache, APK output, local.properties,
release signing key এবং server private signing key ইচ্ছাকৃতভাবে রাখা হয়নি।

Build:
gradle :app:assembleRelease -Pandroid.aapt2FromMavenOverride=/tmp/aapt2-wrapper/aapt2

App-এর remote config signed এবং server-side admin panel থেকে পরিবর্তনযোগ্য।
