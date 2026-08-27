# 🔎 راصد | Rased

### سامانه رصد، پایش و مدیریت اخبار و محتوای رسانه‌ای

**راصد** یک سامانه تحت وب برای **رصد، جمع‌آوری، مدیریت، دسته‌بندی و بررسی اخبار و محتوای منتشرشده در منابع مختلف** است که با هدف ایجاد یک محیط یکپارچه برای پایش اخبار و اطلاعات رسانه‌ای توسعه داده شده است.

این سامانه با استفاده از **Laravel 12** و **Filament 5** توسعه یافته و رابط کاربری آن بر پایه **Tailwind CSS** و **Vite** ساخته شده است.

---

## ✨ قابلیت‌ها

* 📰 مدیریت و ثبت اخبار
* 🔎 جستجو و بررسی اخبار
* 🗂️ دسته‌بندی و مدیریت محتوای خبری
* 📡 رصد منابع و رسانه‌ها
* 🏷️ مدیریت موضوعات و دسته‌ها
* 📊 مدیریت و مشاهده اطلاعات رصدشده
* 🖥️ پنل مدیریت تحت وب
* ⚡ رابط کاربری سریع و واکنش‌گرا
* 🔐 قابلیت توسعه سیستم احراز هویت و سطح دسترسی
* 🧩 معماری قابل توسعه برای اضافه کردن منابع جدید
* 📱 قابلیت توسعه برای استفاده در محیط‌های دسکتاپ و موبایل

---

## 🎯 هدف پروژه

هدف اصلی راصد ایجاد یک **مرکز متمرکز برای پایش اخبار و اطلاعات رسانه‌ای** است.

به‌جای بررسی جداگانه منابع مختلف، اطلاعات مورد نیاز می‌تواند در یک سامانه جمع‌آوری و مدیریت شود تا کاربران بتوانند:

1. منابع مورد نظر را رصد کنند.
2. اخبار و محتوای جدید را دریافت کنند.
3. محتوای مهم را دسته‌بندی کنند.
4. اخبار را جستجو و بررسی کنند.
5. موضوعات و روندهای خبری را بهتر دنبال کنند.
6. اطلاعات مورد نیاز را در یک محیط متمرکز مدیریت کنند.

---

## 🏗️ تکنولوژی‌های استفاده‌شده

| بخش                | فناوری                          |
| ------------------ | ------------------------------- |
| Backend            | PHP                             |
| Framework          | Laravel 12                      |
| Admin Panel        | Filament 5                      |
| Frontend           | Blade / Filament                |
| CSS                | Tailwind CSS 4                  |
| JavaScript         | JavaScript / Axios              |
| Build Tool         | Vite                            |
| Database           | پشتیبانی از دیتابیس‌های Laravel |
| Testing            | Pest                            |
| Dependency Manager | Composer                        |
| Package Manager    | NPM                             |

وابستگی‌های اصلی پروژه در `composer.json` شامل PHP 8.2 یا بالاتر، Laravel 12 و Filament 5 است.

---

## 📁 ساختار پروژه

ساختار اصلی پروژه مطابق ساختار استاندارد Laravel است:

```text
rased/
├── app/
│   ├── Filament/
│   ├── Http/
│   ├── Models/
│   └── ...
│
├── bootstrap/
│
├── config/
│
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│
├── public/
│
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
│
├── routes/
│   ├── web.php
│   └── ...
│
├── storage/
│
├── tests/
│
├── .env.example
├── artisan
├── composer.json
├── package.json
└── vite.config.js
```

---

# 🚀 نصب و راه‌اندازی

## پیش‌نیازها

برای اجرای پروژه حداقل به موارد زیر نیاز دارید:

* PHP `8.2+`
* Composer
* Node.js و NPM
* یک Database مانند MySQL یا SQLite
* Web Server مانند Apache یا Nginx در محیط production

---

## 1. دریافت پروژه

```bash
git clone https://github.com/haghshenasdev/rased.git
cd rased
```

---

## 2. نصب وابستگی‌های PHP

```bash
composer install
```

---

## 3. ایجاد فایل Environment

در Linux / macOS:

```bash
cp .env.example .env
```

در Windows:

```cmd
copy .env.example .env
```

سپس کلید برنامه را ایجاد کنید:

```bash
php artisan key:generate
```

---

## 4. تنظیم دیتابیس

فایل `.env` را باز کرده و اطلاعات دیتابیس را تنظیم کنید.

برای مثال:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rased
DB_USERNAME=root
DB_PASSWORD=
```

---

## 5. اجرای Migration

```bash
php artisan migrate
```

در صورت وجود Seeder:

```bash
php artisan db:seed
```

یا:

```bash
php artisan migrate --seed
```

---

## 6. نصب وابستگی‌های Frontend

```bash
npm install
```

---

## 7. اجرای پروژه در محیط توسعه

در یک ترمینال:

```bash
php artisan serve
```

و در ترمینال دیگر:

```bash
npm run dev
```

سپس برنامه را در آدرس زیر باز کنید:

```text
http://127.0.0.1:8000
```

---

# ⚡ اجرای سریع

پروژه دارای Script آماده برای Setup است.

در صورت آماده بودن محیط توسعه می‌توان از:

```bash
composer run setup
```

استفاده کرد.

این Script نصب Composer، ایجاد `.env`، تولید Application Key، اجرای Migration و نصب و Build وابستگی‌های NPM را انجام می‌دهد.

برای اجرای هم‌زمان سرویس‌های توسعه نیز:

```bash
composer run dev
```

در نظر گرفته شده است که سرور Laravel، Queue و Vite را اجرا می‌کند.

---

# 🛠️ توسعه Frontend

برای اجرای Vite در حالت Development:

```bash
npm run dev
```

برای ایجاد Build نهایی:

```bash
npm run build
```

پروژه از **Vite 7، Tailwind CSS 4 و Laravel Vite Plugin** استفاده می‌کند.

---

# 🧪 تست

برای اجرای تست‌ها:

```bash
php artisan test
```

یا:

```bash
composer run test
```

پروژه از **Pest** و افزونه Laravel آن برای تست استفاده می‌کند.

---

# 🖥️ پنل مدیریت

بخش مدیریت سامانه با **Filament** پیاده‌سازی شده است.

Filament امکان ایجاد یک پنل مدیریتی مدرن برای مدیریت داده‌ها، فرم‌ها، جداول، فیلترها و عملیات مدیریتی را فراهم می‌کند.

وابستگی پروژه به Filament در نسخه `5.x` تعریف شده است.

---

# 📰 معماری سامانه رصد

راصد را می‌توان به چند بخش اصلی تقسیم کرد:

```text
                    ┌────────────────────┐
                    │       منابع        │
                    │     خبر و رسانه    │
                    └─────────┬──────────┘
                              │
                              ▼
                    ┌────────────────────┐
                    │    موتور رصد       │
                    │ جمع‌آوری اطلاعات  │
                    └─────────┬──────────┘
                              │
                              ▼
                    ┌────────────────────┐
                    │ پردازش و مدیریت    │
                    │       اخبار        │
                    └─────────┬──────────┘
                              │
               ┌──────────────┼──────────────┐
               ▼              ▼              ▼
          دسته‌بندی        جستجو          فیلتر
               │              │              │
               └──────────────┼──────────────┘
                              ▼
                    ┌────────────────────┐
                    │    پنل راصد        │
                    │   گزارش و تحلیل    │
                    └────────────────────┘
```

این ساختار امکان توسعه بخش‌های مختلف سامانه را بدون وابستگی شدید بین قسمت‌ها فراهم می‌کند.

---

# 🔮 قابلیت‌های قابل توسعه

معماری پروژه می‌تواند در آینده برای قابلیت‌های زیر توسعه پیدا کند:

### منابع رصد

* تعریف منابع خبری
* تعریف وب‌سایت‌ها
* تعریف کانال‌ها و صفحات رسانه‌ای
* فعال / غیرفعال کردن منابع
* تعیین اولویت منابع

### مدیریت اخبار

* دریافت خودکار اخبار
* جلوگیری از ثبت اخبار تکراری
* ذخیره عنوان و متن خبر
* ذخیره لینک منبع
* ثبت زمان انتشار
* ثبت زمان دریافت
* ذخیره تصویر خبر

### دسته‌بندی

* دسته‌بندی اخبار
* تعریف موضوعات
* برچسب‌گذاری
* تعیین کلمات کلیدی
* تعریف موضوعات مورد رصد

### جستجو و فیلتر

* جستجوی عنوان خبر
* جستجوی متن
* جستجوی منبع
* فیلتر بر اساس تاریخ
* فیلتر بر اساس موضوع
* فیلتر بر اساس منبع

### تحلیل

* تعداد اخبار در بازه‌های زمانی مختلف
* پرتکرارترین موضوعات
* پرتکرارترین منابع
* روند انتشار اخبار
* گزارش‌های آماری
* داشبورد مدیریتی

---

# 🔐 امنیت

برای محیط Production توصیه می‌شود:

* فایل `.env` در Git قرار نگیرد.
* `APP_DEBUG` روی `false` تنظیم شود.
* اطلاعات حساس در Repository قرار نگیرد.
* دسترسی کاربران بر اساس Role و Permission مدیریت شود.
* Database و فایل‌های ذخیره‌شده به‌صورت منظم Backup شوند.
* HTTPS فعال باشد.

نمونه تنظیم Production:

```env
APP_ENV=production
APP_DEBUG=false
```

---

# ⚙️ Queue و پردازش‌های پس‌زمینه

با توجه به ماهیت سامانه‌های رصد، بسیاری از عملیات می‌توانند به‌صورت Background Job اجرا شوند؛ برای مثال:

```text
دریافت منابع
     ↓
بررسی اخبار جدید
     ↓
پردازش
     ↓
تشخیص اخبار تکراری
     ↓
ذخیره در Database
     ↓
دسته‌بندی
     ↓
نمایش در پنل
```

این معماری امکان توسعه سامانه برای تعداد زیادی منبع و حجم بالای اخبار را فراهم می‌کند.

---

# 📌 وضعیت پروژه

راصد یک پروژه در حال توسعه است و ساختار آن برای اضافه شدن قابلیت‌های جدید در نظر گرفته شده است.

> این README متناسب با وضعیت فعلی Repository نوشته شده و با توسعه قابلیت‌های سامانه باید به‌روزرسانی شود.

---

# 🤝 مشارکت

برای مشارکت در توسعه پروژه:

```bash
git clone https://github.com/haghshenasdev/rased.git
cd rased
```

پس از ایجاد تغییرات، می‌توانید یک Pull Request ارسال کنید.

---

# 📄 License

این پروژه تحت مجوز **MIT** منتشر شده است.

---

## 👨‍💻 توسعه‌دهنده

**HaghshenasDev**

GitHub:

https://github.com/haghshenasdev

Repository:

https://github.com/haghshenasdev/rased

---

## ⭐ درباره راصد

**راصد** با هدف ایجاد یک سامانه یکپارچه برای **رصد، مدیریت و تحلیل اخبار و محتوای رسانه‌ای** طراحی شده است.

هدف پروژه ایجاد بستری قابل توسعه است که بتواند منابع مختلف را پایش کرده و اطلاعات جمع‌آوری‌شده را در قالب یک پنل مدیریتی منظم، قابل جستجو و قابل تحلیل در اختیار کاربران قرار دهد.

> 🔎 **راصد؛ همه‌چیز را ببین، چیزی را از دست نده.**

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
