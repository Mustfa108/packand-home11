# متطلبات التشغيل — HumaScale

## ما يجب توفيره

### برمجيات
- PHP 8.3+
- Composer
- MySQL
- Node.js 18+
- npm

### مفاتيح وإعدادات
- مفتاح Gemini (`GEMINI_API_KEY`) — يمكن تعديله لاحقاً من لوحة الأدمن
- بيانات قاعدة البيانات في `.env`
- مفاتيح Reverb للدردشة الحية (موجودة أمثلة في `.env.example`)

### تشغيل الباك إند

```bash
cd packand-home11
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

في طرفية ثانية:

```bash
php artisan queue:work
```

في طرفية ثالثة (للدردشة):

```bash
php artisan reverb:start
```

### تشغيل الفرونت إند

```bash
cd front-home1
copy .env.example .env
npm install
npm run dev
```

افتح:

`http://localhost:5173`

API المتوقع:

`http://127.0.0.1:8000`

### بعد التشغيل
1. سجّل دخول الأدمن
2. من `/admin/settings` ضع مفتاح Gemini وروابط التواصل
3. أنشئ/انشر إصدار استبيان إذا لزم
4. اختبر التقييم، PDF، تقييم المشروع، الخريطة، والدردشة
