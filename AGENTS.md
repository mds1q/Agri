# Secure Web Portal AI Work Rules

এই project-এ কাজ করা যেকোনো AI agent-এর জন্য নিয়ম:

## কাজ শুরুর আগে

1. `chat-history/PROJECT-CONTEXT.md` সম্পূর্ণ পড়তে হবে।
2. `chat-history/WORKLOG.md`-এর সর্বশেষ entry পড়তে হবে।
3. বর্তমান source, package এবং user-এর existing changes অক্ষত রাখতে হবে।
4. প্রয়োজন ছাড়া `storage/`, `private/`, signing key, saved config বা পুরোনো history মুছা যাবে না।

## কাজ করার সময়

1. পরিবর্তনের আগে সংশ্লিষ্ট source ও deployment structure যাচাই করতে হবে।
2. একই feature-এর একাধিক deployment copy থাকলে সব প্রাসঙ্গিক copy মিলিয়ে রাখতে হবে।
3. destructive operation করার আগে exact target যাচাই করতে হবে।
4. user-এর অনুমতি ছাড়া external hosting, account বা third-party service-এ write করা যাবে না।
5. source, package name, signing, remote config, Admin Panel, Firebase বা deployment workflow পরিবর্তন হলে একই কাজের মধ্যে সংশ্লিষ্ট setup documents update করতে হবে।
6. setup document-এর command, file path বা expected output source-এর সঙ্গে না মিললে source change শেষ করার আগে document-ও সংশোধন করতে হবে।

## কাজ শেষ করার আগে

`chat-history/WORKLOG.md`-এ নতুন date-stamped entry যোগ করতে হবে। Entry-তে লিখতে হবে:

- অনুরোধ বা উদ্দেশ্য
- কারণ বা সমস্যা শনাক্তকরণ
- সম্পন্ন ধাপ
- পরিবর্তিত ফাইল ও package
- কীভাবে যাচাই করা হয়েছে
- build hash বা গুরুত্বপূর্ণ output, যদি থাকে
- live deployment বা user-এর পরবর্তী করণীয়

পুরোনো history মুছা বা rewrite করা যাবে না। কোনো পুরোনো তথ্য ভুল হলে নতুন entry-তে তার সংশোধনের কারণ লিখতে হবে। স্থায়ী architecture বা deployment rule বদলালে `chat-history/PROJECT-CONTEXT.md`-ও আপডেট করতে হবে।

## Documentation maintenance

1. নতুন project setup-এর মূল reference হলো `NEW-PROJECT-SETUP-BN.md` এবং হাতে-কলমে reference হলো `NEW-PROJECT-HANDS-ON-BN.md`।
2. ভবিষ্যতে এই project-এর কোনো implementation, package, key, API endpoint, hosting layout, Firebase flow বা installer বদলালে পরিবর্তিত ধাপের সঙ্গে এই দুই document-ই মিলিয়ে update করতে হবে।
3. Documentation update-এর সময় `README.md` link, `chat-history/PROJECT-CONTEXT.md` reference এবং `chat-history/WORKLOG.md` date-stamped record পরীক্ষা করতে হবে।
4. Worklog entry-তে কোন document কেন update হয়েছে, কী যাচাই হয়েছে এবং live deployment বাকি কি না লিখতে হবে।
5. কোনো setup rule source-এর সঙ্গে conflict করলে বর্তমান source ও deployment structure যাচাই করে নতুন worklog entry-তে সংশোধনের কারণ লিখতে হবে; পুরোনো history মুছা যাবে না।

## উত্তর দেওয়ার নিয়ম

কাজ শেষে user-কে সংক্ষেপে জানাতে হবে:

1. কী পরিবর্তন হয়েছে
2. কী যাচাই হয়েছে
3. কোন file বা package ব্যবহার করতে হবে
4. live deployment বাকি থাকলে তা স্পষ্টভাবে উল্লেখ করতে হবে
