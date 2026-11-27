# Osuthuru host කිරීම (Oracle Cloud Always Free)

Site එක, database එක, ML service එක සහ HTTPS එක **server එකක් ඇතුළේම** දුවයි. Commands server එකේ (Linux) terminal එකේ run කරන්න.

> Menu නම් සහ free සීමා කලින් කලට වෙනස් වේ. Sign up වන විට Oracle පිටුවල වත්මන් විස්තර බලන්න.

## 0. පටන් ගන්න කලින්

| අවශ්‍ය දේ | සටහන |
|---|---|
| Credit/debit card | Oracle account verification සඳහා සාමාන්‍යයෙන් අවශ්‍යයි |
| Domain එකක් | Free: [DuckDNS](https://www.duckdns.org) subdomain (උදා: `osuthuru.duckdns.org`). ගෙවන: `.lk` හෝ `.com` |
| Email යවන SMTP | **අනිවාර්යයි**, නැත්නම් අලුත් accounts තහවුරු කළ නොහැක. උදා: Gmail "App password" (`smtp.gmail.com`, port `587`) |

## 1. Server එක හදන්න

1. Oracle Cloud account එකක් හදන්න. **Home region** එක පසුව වෙනස් කළ නොහැක.
2. **Compute → Instances → Create instance**
   - Image: **Ubuntu 24.04**
   - Shape: **Ampere (VM.Standard.A1.Flex)**, "Always Free eligible" සලකුණ බලන්න. 2 OCPU, 12 GB (හෝ වත්මන් free සීමාව තුළ).
   - **SSH key** එක download කර ආරක්ෂිතව තබා ගන්න.
3. "Out of capacity" ආවොත් පසුව හෝ වෙනත් availability domain එකකින් උත්සාහ කරන්න.
4. Instance එකේ **Public IP** එක සටහන් කරගන්න.

## 2. Ports 80 සහ 443 open කරන්න

**Oracle console:** Instance → Virtual Cloud Network → Security List → **Add Ingress Rules**: Source `0.0.0.0/0`, TCP, destination port `80`; නැවත `443`.

**Server එක ඇතුළේ** (පියවර 3 න් සම්බන්ධ වූ පසු):

```bash
sudo iptables -I INPUT 1 -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 1 -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

## 3. සම්බන්ධ වී Docker install කරන්න

ඔයාගේ PC එකේ PowerShell:

```powershell
ssh -i C:\path\to\key ubuntu@SERVER_IP
```

Server එකේ:

```bash
sudo apt update && sudo apt upgrade -y
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker ubuntu
exit
```

නැවත `ssh` කරන්න.

## 4. Domain එක යොමු කරන්න

DuckDNS (හෝ domain සමාගමේ DNS) හි **A record** එක server IP එකට දමන්න. `ping ඔබේ-domain` එකෙන් නිවැරදි IP එක පෙනිය යුතුයි.

## 5. Site එක install කරන්න

```bash
git clone https://github.com/Priyasad010/Osuthuru.git
cd Osuthuru
sh bin/prod/make_env.sh ඔබේ-domain ඔබේ@email.com
nano .env
```

`.env` හි පුරවන්න (අනෙක් ඒවා script එක හදා ඇත):

| Setting | අගය |
|---|---|
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM` | ඔයාගේ SMTP විස්තර |
| `LLM_API_KEY` | Gemini key (නැත්නම් assistant එක සරල පිළිතුරු දෙයි) |
| `PAYHERE_MERCHANT_ID`, `PAYHERE_SECRET` | PayHere (ඔබේ domain එකට හැදූ secret එක) |

`ADMIN_PASSWORD` එක මේ file එකේම තියෙනවා; කියවා සටහන් කරගන්න. Save: `Ctrl+O`, `Enter`, `Ctrl+X`.

```bash
sh bin/prod/deploy.sh --first
```

පළමු වතාවේ මිනිත්තු 10–15ක් යා හැක. ඉවර වූ පසු `https://ඔබේ-domain` open කරන්න. Certificate එක ස්වයංක්‍රීයව ලැබේ.

## 6. Install කළ පසු

මුලින්ම email යන බව තහවුරු කරන්න (ඔබේම ලිපිනයට):

```bash
docker compose -f docker-compose.prod.yml exec web php bin/test_mail.php ඔබේ@email.com
```

`Sent` ආවොත් inbox සහ spam බලන්න. `Not sent` ආවොත් `.env` හි `MAIL_` අගයන් නිවැරදි කර නැවත run කරන්න.

1. `https://ඔබේ-domain/admin/login` → login → **Change Password**.
2. **Shop Settings:** බැංකු විස්තර සහ contact විස්තර.
3. Policy පිටු තහවුරු කරන්න; **Herb Text**, **Safety Data**, **Quiz Editor** හි වෛද්‍යවරයෙකු තහවුරු කළ පෙළ දමන්න.
4. පරීක්ෂා කරන්න: register → email → login → order → admin → QR scan → phone එකේ install.
5. QR labels print කරන්නේ **domain එක සකසා පසුවයි**.

**Local data server එකට යන්නේ නැහැ.** Server එකේ ආරම්භ වන්නේ catalogue එකෙන් පමණි; demo orders, customers සහ demo පෙළ නැහැ.

## 7. ස්වයංක්‍රීය backup

```bash
crontab -e
```

අන්තිමට මේ පේළිය එකතු කරන්න (හැමදාම රාත්‍රී 2.30ට, server වේලාවෙන්):

```
30 2 * * * cd /home/ubuntu/Osuthuru && sh bin/prod/backup.sh >> /home/ubuntu/osuthuru-backup.log 2>&1
```

දැන්ම පරීක්ෂා කරන්න: `sh bin/prod/backup.sh`. Backups `~/osuthuru-backups` හි තියෙනවා. **සතියකට වරක් ඔයාගේ PC එකට copy කරගන්න** (server එක නැති වුණොත් backup ද නැති වේ):

```powershell
scp -i C:\path\to\key -r ubuntu@SERVER_IP:osuthuru-backups C:\Users\ඔබ\osuthuru-backups
```

Database එකක් ආපසු යෙදීමට (**දැනට ඇති data overwrite වේ**):

```bash
gunzip -c ~/osuthuru-backups/db_DATE.sql.gz | docker compose -f docker-compose.prod.yml exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot "$MYSQL_DATABASE"'
```

## 8. Update කිරීම

```bash
cd ~/Osuthuru
sh bin/prod/backup.sh
git pull
sh bin/prod/deploy.sh
```

## 9. ගැටලු

| පෙනෙන දේ | බලන්න |
|---|---|
| Site එක open නොවේ | Ports (පියවර 2), DNS, `docker compose -f docker-compose.prod.yml ps` |
| Certificate error | Domain එක server IP එකට යොමු වී ඇත්ද; `docker compose -f docker-compose.prod.yml logs caddy` |
| Error පිටුවක් | `docker compose -f docker-compose.prod.yml logs web --tail 50` |
| Email නොයයි | `.env` හි `MAIL_` අගයන් |

Oracle, දිගටම භාවිත නොවන free instances ආපසු ගත හැකියි. Console එකේ email notices බලන්න.

## 10. Commercial ලෙස භාවිත කිරීමට පෙර

Technical පැත්තෙන් site එක ක්‍රියා කළත්, **ව්‍යාපාරයක් ලෙස** දියත් කිරීමට පෙර මේවා සම්පූර්ණ විය යුතුයි:

- [ ] **Product photos:** දැනට තියෙන්නේ වෙනත් සමාගම්වල packaging photos. ඔයා ගත් photos හෝ ලිඛිත අවසර.
- [ ] **Products සහ prices:** ඔයා ඇත්තටම විකුණන භාණ්ඩ, ඔයාගේ මිල සහ තොග.
- [ ] **නීතිමය:** ව්‍යාපාර ලියාපදිංචිය; ආයුර්වේද සහ රූපලාවන්‍ය නිෂ්පාදන විකිණීමේ අවසර (ආයුර්වේද දෙපාර්තමේන්තුව සහ අදාළ අධිකාරීන්ගෙන් විමසන්න); පෞද්ගලික දත්ත ආරක්ෂණ පනත.
- [ ] **පෙළ:** Practitioner review, සිංහල review, policy පිටු නීති උපදේශකයෙකු සමඟ.
- [ ] **නම:** "Osuthuru" සඳහා trademark සහ domain පරීක්ෂාව.
- [ ] **ගෙවීම්:** PayHere **live** merchant account, `PAYHERE_SANDBOX=false`.
- [ ] **AI:** Gemini **free tier** හි යවන පෙළ Google හි සේවා දියුණු කිරීමට භාවිත විය හැක. සැබෑ customers සඳහා paid tier එක සහ [වත්මන් කොන්දේසි](https://ai.google.dev/gemini-api/terms) බලන්න.
- [ ] **Hosting:** Always Free හි uptime සහතිකයක් නැහැ. ආදායම එන විට ගෙවන server එකක් සලකා බලන්න.
- [ ] **Code හිමිකම:** කණ්ඩායම් හෝ university project එකක් නම් අනෙක් අයගේ සහ ආයතනයේ එකඟතාව.
- [ ] **Picture credits** පිටුව තබා ගන්න (Wikimedia photos වල කොන්දේසියක්).

භාවිත කළ libraries (MIT, BSD, Apache, LGPL) commercial භාවිතයට ඉඩ දෙයි.
