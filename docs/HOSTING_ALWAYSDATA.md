# Osuthuru: alwaysdata Free හි host කිරීම (card අවශ්‍ය නැහැ)

> **සීමා:** 1 GB disk, 256 MB RAM. **ලාභ ලබන ව්‍යාපාරයකට free plan එක පාවිච්චි කරන්න බැහැ.** Python ML service එක නැහැ; recommendations සහ forecast සරල PHP ක්‍රමයකට වැඩ කරයි.
> Menu නම් කලින් කලට වෙනස් විය හැක.

පහත `ACCOUNT` යන තැනට ඔයාගේ account නම දාන්න.

## 1. Account එක හදන්න
1. https://www.alwaysdata.com/en/register/ → **Free** plan.
2. **Account name** එකක් තෝරන්න (උදා: `osuthuru`). Site address එක `https://ACCOUNT.alwaysdata.net` වේ.
3. Email එක verify කර admin panel එකට login වන්න.

## 2. PHP සහ SSH
1. **Environment → PHP**: version **8.3** තෝරා save.
2. **Remote access → SSH**: ඔයාගේ user එක edit කර **password login** on කරන්න. Password එකක් නැත්නම් මෙතැනම හදන්න.

## 3. Database
1. **Databases → MySQL → Add a database**: නම `ACCOUNT_osuthuru`.
2. User `ACCOUNT` ට එම database එකට **all privileges** දෙන්න.
3. **Users** tab එකේ user ගේ **database password** එක සකසා සටහන් කරගන්න.

## 4. Gmail App password
https://myaccount.google.com/apppasswords → `Osuthuru-server` නමින් අලුත් එකක් හදන්න (අකුරු 16).

## 5. Install
PC එකේ PowerShell:

```powershell
ssh ACCOUNT@ssh-ACCOUNT.alwaysdata.net
```
`yes` type කර SSH password එක දෙන්න (type කරන විට කිසිවක් නොපෙනේ). **මෙතැන් සිට commands server එකේයි:**

```bash
git clone https://github.com/Priyasad010/Osuthuru.git
cd Osuthuru
php bin/shared/setup_env.php
```
ප්‍රශ්න වලට පිළිතුරු දෙන්න. `[ ]` ඇතුළේ පෙනෙන අගය හරි නම් **Enter** ඔබන්න. Passwords type කරන විට නොපෙනේ.

⚠️ අන්තිමට පෙනෙන **Administrator password** එක සටහන් කරගන්න.

```bash
sh bin/shared/deploy.sh --first
```
`[FAIL]` පේළියක් ආවොත් එහි උපදෙස අනුගමනය කර **නැවත** මේ command එකම run කරන්න (`PHP extension` නම්: **Environment → PHP → php.ini** හි එම `extension=...` පේළිය එකතු කරන්න). අන්තිමට `Done.` ආ යුතුයි.

## 6. Website එක යොමු කරන්න
**Web → Sites** → දැනට ඇති `ACCOUNT.alwaysdata.net` site එක edit කරන්න:

| Setting | අගය |
|---|---|
| Type | **PHP** |
| PHP version | **8.3** |
| Root directory | `Osuthuru/public/` |
| SSL → **Force HTTPS** | on |

Save කර මිනිත්තුවක් ඉන්න.

## 7. පරීක්ෂා කරන්න
Server එකේ:

```bash
php bin/shared/check_site.php
php bin/test_mail.php ඔබේ@email.com
```
- `All checks passed.` සහ `Sent.` ආ යුතුයි.
- **`Visitors cannot fake their address` FAIL** වුණොත්: `nano .env` හි `PROXY_IP_HEADER=` හිස් කර නැවත check කර, output එක මට එවන්න.

Browser එකේ:
1. `https://ACCOUNT.alwaysdata.net/admin/login` → login → **Change Password**.
2. **Settings**: බැංකු සහ contact විස්තර.
3. Register → email verify → order → admin එකෙන් බලන්න.
4. **Reports → PDF** download කර බලන්න (memory සීමාව නිසා).

**PayHere (අවශ්‍ය නම්):** Sandbox හි මේ domain එක add කර, `nano .env` හි `PAYHERE_MERCHANT_ID`, `PAYHERE_SECRET` දමන්න.

## 8. ස්වයංක්‍රීය backup
**Advanced → Scheduled tasks → Add**: command එක, දිනකට වරක්:

```
sh /home/ACCOUNT/Osuthuru/bin/shared/backup.sh
```
දැන්ම බලන්න: `sh bin/shared/backup.sh`. දින 7ක backups `~/osuthuru-backups` හි තියේ. **සතියකට වරක් PC එකට copy කරගන්න** (PC PowerShell):

```powershell
scp -r ACCOUNT@ssh-ACCOUNT.alwaysdata.net:osuthuru-backups C:\Users\ඔබ\
```

## 9. Update කිරීම
```bash
cd ~/Osuthuru && sh bin/shared/backup.sh && git pull && sh bin/shared/deploy.sh
```

## 10. ගැටලු

| පෙනෙන දේ | කරන්න |
|---|---|
| alwaysdata default පිටුවක් / 404 | පියවර 6: Root directory `Osuthuru/public/` |
| Error පිටුවක් (500) | Panel හි site logs, නැත්නම් `ls ~/admin/logs`; `php bin/shared/check_server.php` |
| Database connection FAIL | `nano .env` හි `DB_` අගයන් සහ පියවර 3 |
| `Not sent` | `.env` හි `MAIL_` අගයන් |
| Disk පිරී ඇත | `du -sh ~/*` |

⚠️ `.env`, passwords **කාටවත් යවන්න එපා**. Error එකක් එවනවා නම් passwords නොපෙනෙන බව බලන්න.
