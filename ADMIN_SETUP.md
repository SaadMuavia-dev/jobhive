# JobHive — Admin Setup Guide (Urdu)

## ✅ Kya Fix Hua?

### 1. Navbar Bug Fix
**Masla kya tha:** Login ke baad bhi Login/Signup buttons nazar aate the.
**Fix:** `style="display:none!important"` mein `!important` remove kiya — ab JS theek se kaam karta hai.
Yeh 5 files mein fix hua: index.html, jobs.html, companies.html, about.html, contact.html

---

## 🔐 Admin User Kaise Banayein

### Method 1: SQL se (Recommended)

Pehle apna password hash banao. Terminal mein likho:

```bash
php -r "echo password_hash('TumharaPassword123', PASSWORD_BCRYPT, ['cost'=>12]);"
```

Jo hash output aaye, use `jobhive_schema.sql` mein is jagah replace karo:

```sql
INSERT IGNORE INTO users (name, email, password_hash, role)
VALUES (
  'Admin',
  'admin@jobhive.com',
  'YAHAN_APNA_HASH_DALO',
  'admin'
);
```

Phir schema run karo:
```bash
mysql -u root -p < jobhive_schema.sql
```

### Method 2: Kisi existing user ko admin banana

Agar pehle se koi user register kiya hua hai, phir admin banana hai:

```sql
UPDATE users SET role = 'admin' WHERE email = 'tumhara@email.com';
```

---

## 🚀 Admin Panel Use Karna

1. **Login** — `index.html` par jaao aur **admin email + password** se login karo
2. **Admin Panel** — Login ke baad Navbar mein user name click karo → **Admin Panel** link nazar aayega (sirf admin ko)
3. **Direct URL** — `admin.html` par directly ja sako

---

## 📋 Admin Panel Features

### Dashboard
- Total active jobs, users, applications, pending apps ka count

### Jobs Management
- Sari jobs ki list with application count
- Job active/inactive toggle kar sako
- Job delete kar sako

### New Job Post
- Poori detail ke saath nai job post karo
- Featured / Remote toggle
- Salary range set karo

### Applications
- Sari applications dekho
- Status filter: Pending, Reviewed, Shortlisted, Rejected, Hired
- Har application ki full detail dekho (cover letter, experience, etc.)
- Status seedha table se change karo (dropdown)
- Applicant ko reply bhejo (reply database mein save hota hai)

### Contact Messages
- Contact form se aaye messages dekho
- Unread messages highlight hote hain
- Mark as read kar sako

---

## 📁 Naye Files

| File | Kaam |
|------|------|
| `admin.html` | Admin Panel — full dashboard |
| `api/admin.php` | Admin API — sab backend calls |

---

## ⚠️ Security Tips

1. **Default password ZAROOR change karo** — `admin@jobhive.com` ka password change karo
2. **`db.php`** mein apna MySQL password dalo
3. Production mein `.htaccess` se `api/` folder protect karo:
   ```
   <Files "admin.php">
     Order Deny,Allow
     Deny from all
   </Files>
   ```
   (Session check already hai lekin extra layer)

---

## 🛠️ Database Setup

```bash
# Pehli baar setup karna
mysql -u root -p < jobhive_schema.sql

# Admin user dobara banana (agar pehle se schema run hua tha)
mysql -u root -p jobhive < admin_only.sql
```

**db.php mein apna config change karo:**
```php
define('DB_USER', 'tumhara_mysql_user');
define('DB_PASS', 'tumhara_mysql_password');
```

---

## Reply Feature Note
Reply abhi **database mein save** hoti hai. 
Email bhejne ke liye PHP `mail()` ya PHPMailer integrate karna hoga (future upgrade).
