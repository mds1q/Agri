# Project Context

এই ফাইলটি প্রজেক্টের স্থায়ী পরিচিতি ও গুরুত্বপূর্ণ সিদ্ধান্তের সংক্ষিপ্ত নথি। নতুন কোনো কাজ শুরু করার আগে এই ফাইল এবং [`WORKLOG.md`](WORKLOG.md) পড়তে হবে।

## প্রজেক্ট

- নাম: Secure Web Portal (Android)
- ধরন: Native Android WebView shell
- মূল উদ্দেশ্য: Remote JSON configuration ব্যবহার করে একটি ওয়েব পোর্টাল Android অ্যাপে দেখানো
- প্যাকেজ আইডি: `app.agriwealth.portal`

## গুরুত্বপূর্ণ কাঠামো

- `app/` — Android অ্যাপের source code
- `php-control-panel/` — PHP admin panel এবং configuration API
- `remote-config/` — remote JSON configuration এবং signature
- `tools/` — configuration signing script
- `chat-history/` — কাজের ইতিহাস, সিদ্ধান্ত এবং project context
- `README.md` — প্রজেক্টের সাধারণ setup ও configuration নির্দেশনা
- `NEW-PROJECT-SETUP-BN.md` — নতুন package, key, config ও Admin Panel সহ আলাদা project তৈরির ধাপে ধাপে নির্দেশনা
- `NEW-PROJECT-HANDS-ON-BN.md` — command-by-command hands-on setup, expected output এবং verification checkpoint
- `NEW-SEPARATE-PROJECT-HANDS-ON-BN.md` — পুরোনো project থেকে সম্পূর্ণ আলাদা App ও Admin Panel তৈরির step-by-step runbook

## কাজের নিয়ম

1. নতুন কাজ শুরুর আগে `PROJECT-CONTEXT.md` এবং `WORKLOG.md` পড়তে হবে।
2. কাজের প্রয়োজন হলে সংশ্লিষ্ট source file, README এবং আগের work log দেখে সিদ্ধান্ত নিতে হবে।
3. কাজ শেষ হলে `WORKLOG.md`-তে তারিখসহ সম্পন্ন ধাপ, পরিবর্তিত ফাইল, যাচাই এবং বাকি কাজ লিখতে হবে।
4. কোনো গুরুত্বপূর্ণ architecture decision, configuration rule বা deployment তথ্য স্থায়ী হলে এই ফাইলেও যোগ করতে হবে।
5. পুরোনো তথ্য ভুল হলে মুছে না দিয়ে সংশোধনের কারণ ও তারিখ `WORKLOG.md`-তে লিখতে হবে।

## Documentation maintenance rule

- `NEW-PROJECT-SETUP-BN.md` এবং `NEW-PROJECT-HANDS-ON-BN.md` নতুন project তৈরির স্থায়ী setup reference।
- Source, package, signing, remote config, Admin Panel, Firebase বা deployment structure বদলালে সংশ্লিষ্ট document একই কাজের মধ্যে update করতে হবে।
- Document update-এর পরে `README.md` link, এই context reference এবং `WORKLOG.md`-এর date-stamped record মিলিয়ে রাখতে হবে।
- কোনো পুরোনো নির্দেশনা ভুল হলে তা মুছে না ফেলে নতুন worklog entry-তে সংশোধনের কারণ লিখতে হবে।

## বর্তমান অবস্থা

- Remote configuration RSA/SHA-256 signature দিয়ে যাচাই করা হয়।
- Remote configuration পাওয়া না গেলে bundled configuration ব্যবহার করা হয়।
- PHP admin panel configuration update ও signing workflow পরিচালনা করে।
- App প্রতি ৫ সেকেন্ডে `portal-config.version`-এর ছোট hash check করে; hash বদলালে তবেই JSON ও signature download করে।
- Release build একই signing key ব্যবহার করে তৈরি করতে হয়।

বিস্তারিত নিয়ম ও পরিবর্তনের ধারাবাহিকতা `WORKLOG.md`-তে রাখা হবে।
