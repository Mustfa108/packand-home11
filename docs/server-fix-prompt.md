# برومبت إصلاح سيرفر HumaScale

انسخ البرومبت التالي وأعطه لمسؤول السيرفر أو لوكيل DevOps:

---

أنت مهندس DevOps مسؤول عن إصلاح بيئة إنتاج HumaScale (Laravel API + React SPA + Reverb). نفّذ التالي بالترتيب وتحقق بعد كل خطوة.

## 1) CORS و FRONTEND_URL

في ملف `.env` الخاص بالباك إند (`packand-home11`):

```env
FRONTEND_URL=https://YOUR_FRONTEND_ORIGIN
FRONTEND_URL_ALT=https://OPTIONAL_SECOND_ORIGIN
APP_URL=https://YOUR_API_ORIGIN
```

مهم:

- القيمة يجب أن تطابق أصل الفرونت تماماً (scheme + host + port إن وُجد).
- بدون slash في النهاية.

ثم نفّذ:

```bash
php artisan config:clear
php artisan cache:clear
```

تحقق:

```bash
curl -i -X OPTIONS "https://YOUR_API_ORIGIN/api/community-chat/messages" \
  -H "Origin: https://YOUR_FRONTEND_ORIGIN" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: authorization,content-type"
```

يجب أن ترى رأس:
`Access-Control-Allow-Origin: https://YOUR_FRONTEND_ORIGIN`

## 2) Gemini / الذكاء الاصطناعي

```env
GEMINI_API_KEY=YOUR_REAL_KEY
GEMINI_MODEL=gemini-2.0-flash
```

أو ضع المفتاح من لوحة الأدمن: `/admin/settings/ai`.

الأولوية في الكود: قيمة `SiteSettingService` (لوحة الأدمن) إن وُجدت وغير فارغة، وإلا `GEMINI_API_KEY` من `.env`. تأكد أن القيمتين غير متعارضتين (مفتاح منتهٍ في الأدمن يحجب المفتاح الصحيح في `.env`).

شغّل Queue Worker بشكل مستمر عبر Supervisor أو systemd (وليس يدوياً ينقطع):

```bash
php artisan queue:work --sleep=1 --tries=3 --timeout=120
```

بدون `queue:work` لن يكتمل **الملخص الذكي** بعد التقييم (`ProcessAssessmentAI`).
«التحليل الذكي» و«اسأل عن نتيجتك» متزامنان ولا يعتمدان على الطابور، لكنهما يحتاجان مفتاح Gemini وبروفيل منظمة مكتمل (`org_type` + `org_size`) للتحليل.

تحقق من المهام الفاشلة:

```bash
php artisan queue:failed
```

راجع `storage/logs/laravel.log` لأي استثناءات Gemini / `ProcessAssessmentAI` / `project-reviews`.
تأكد أن السيرفر يصل لـ Gemini API (لا حجب firewall/DNS)، وأن المفتاح غير محظور أو منتهٍ، وأن حدود Rate Limiting من Google غير مستنفدة.

## 3) Reverb / WebSocket (دردشة المجتمع الفورية)

المشكلة الشائعة الحالية:

- الفرونت يتصل بـ `reverb.sci-syria.org` على المنفذ 80
- عملية Reverb تستمع داخلياً على 8080
- DNS للنطاق غير معروف أو لا يوجد Proxy/SSL

اضبط DNS:

- سجل A/AAAA لـ `reverb.sci-syria.org` (أو النطاق الذي تستخدمه) يشير لسيرفر التطبيق.

في `.env` الباك:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=humascale
REVERB_APP_KEY=humascale-reverb-key
REVERB_APP_SECRET=humascale-reverb-secret
REVERB_HOST=reverb.sci-syria.org
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

شغّل Reverb:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

أضف Proxy في Nginx/Caddy:

- المنفذ العام 443 (WSS) يوجّه إلى `127.0.0.1:8080`
- فعّل WebSocket upgrade headers
- ثبّت شهادة SSL للنطاق

في فرونت الإنتاج `.env` / build:

```env
VITE_API_BASE_URL=https://YOUR_API_ORIGIN/api
VITE_REVERB_APP_KEY=humascale-reverb-key
VITE_REVERB_HOST=reverb.sci-syria.org
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

ثم أعد البناء:

```bash
npm ci
npm run build
```

مهم: لا تستخدم `scheme=https` مع port 80.

## 4) تحقق نهائي

1. تسجيل دخول مستخدم → يجب ألا يظهر CORS في Network.
2. إرسال رسالة في `/chat` عبر REST يجب أن ينجح (حتى بدون realtime).
3. مع Reverb الصحيح تظهر الرسائل فوراً للطرف الآخر.
4. توليد تحليل ذكي لتقييم مكتمل بعد إكمال ملف المنظمة.
5. تنزيل PDF عربي غير مقلوب (بعد نشر كود mPDF).

إذا فشل أي بند: أعد قيمة env الفعلية + لقطة من Headers في المتصفح + مخرجات `php artisan reverb:start` و`queue:work`.

---
