# Push notification setup

Firebase FCM এবং OneSignal native SDK app-এ যুক্ত করা হয়েছে। Welcome popup-এর remote image feature-ও চালু আছে। Live push চালু করতে Admin panel-এ provider public settings এবং server credentials বসাতে হবে। এরপর notification-এর title, message, image, target এবং send action Admin panel থেকে নিয়ন্ত্রণ করা যাবে; প্রতিটি message-এর জন্য APK update লাগবে না।

এই integration-এ provider হিসেবে একটি বা দুটোই বেছে নেওয়া যাবে:

- OneSignal: OneSignal App ID এবং server-side App API Key প্রয়োজন। API key কখনো app-এর JSON বা APK-তে রাখা যাবে না।
- Firebase FCM: Firebase project ID, Application ID, API key, sender ID এবং server-side service account প্রয়োজন। service account private key শুধু server-এর private folder-এ থাকবে।

Admin panel-এ provider selection, private credential storage এবং all-subscriber send endpoint যোগ করা হয়েছে। Firebase-এর সর্বশেষ Messaging SDK-এর জন্য app-এর minimum Android API 24 করা হয়েছে। বিদ্যমান signed remote config, HTTPS option, screenshot protection, debugger block এবং upload validation অক্ষত রাখা হয়েছে।
