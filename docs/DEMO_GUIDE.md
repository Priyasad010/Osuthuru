# Viva demo guide (විනාඩි 8)

Roadmap එකේ 7 වන කොටසේ කතා පිළිවෙළ, demo data සමඟ. Button නම් ඉංග්‍රීසි UI එකේ පෙනෙන ලෙසමය.

## 1. කලින් දවසේ / පටන් ගන්න කලින්

1. Docker Desktop open කර project folder එකේ: `docker compose up -d --wait`
2. Demo data නැත්නම් පමණක්: `docker compose exec web php bin/seed.php --demo`
3. Network එක වෙනස් නම්: `pwsh bin/set_lan_url.ps1`, ඉන්පසු QR labels **නැවත** download කරන්න (පැරණි labels වල තියෙන්නේ පැරණි address එක).
4. AI පරීක්ෂාව: `docker compose exec web php bin/test_llm.php` → `OK` එන්න ඕන.
5. **Internet අවශ්‍යයි** (Bootstrap design එක CDN එකෙන්, assistant එක Gemini වලින්).
6. Phone එක PC එක තියෙන Wi-Fi එකටම connect කර, label එකක් scan කර බලන්න.
7. Tabs තුනක් සූදානම් කරන්න:
   - Customer: `http://localhost:8080/login` → `nimali@demo.invalid` / `DemoPass123!`
   - Admin (**වෙනම browser එකක් හෝ private window එකක**): `http://localhost:8080/admin/login` → `.env` හි admin account එක
   - Mailpit: `http://localhost:8025`
8. Admin → **Batches & QR Labels** → `DEMO-NEEM-01` සහ `DEMO-BALM-03` labels download කර print කරන්න (හෝ screen එකේ තියන්න).
9. "Expired" පෙන්වීමට කැමති නම්: එම පිටුවේ අලුත් batch එකක් add කරන්න. Manufactured දිනය පසුගිය අවුරුද්ද, expiry දිනය ඊයේ.
10. Dashboard හි **Low stock** list එක demo data වල හිස්ය. පෙන්වීමට කැමති නම් **Manage Products & Stock** හි එක් product එකක stock එක 2 කරන්න (පසුව ආපසු දාන්න).

11. Bank transfer පෙන්වීමට කැමති නම් admin → **Shop Settings** හි බැංකු විස්තර (demo සඳහා පැහැදිලිව "DEMO" කියා) දමන්න. නැත්නම් checkout හි Cash on Delivery සහ PayHere පමණක් පෙනේ.

12. **Session කාලය:** admin පිටු මිනිත්තු 30ක් භාවිත නොකළොත් logout වේ (customer පැය 2යි). Demo පටන් ගැනීමට මොහොතකට පෙර login වන්න.

## 2. Demo පිළිවෙළ

| # | කාලය | කරන්න | කියන්න |
|---|---|---|---|
| 1 | 0:45 | Home page (banner එකේ herb එකක් තෝරන්න) → **සිං** → **EN** | "මුළු site එකම ඉංග්‍රීසි සහ සිංහල." |
| 2 | 1:15 | **Dosha Quiz** → ප්‍රශ්න 12 → **See My Result** | "පිළිතුරු අනුව නිෂ්පාදන යෝජනා වෙනවා." |
| 3 | 1:00 | Chat බොත්තම → `කොහොඹ ඇති නිෂ්පාදන මොනවාද?` → **Send** | "පිළිතුරු එන්නේ අපේ herb සහ product data වලින් පමණයි." |
| 4 | 0:45 | **Herbal Dictionary** → Neem → එම herb පිටුවේ Aliver Neem Oil | "Dictionary එකයි shop එකයි එකම knowledge base එකක්." |
| 5 | 1:15 | **Add to Cart** → cart → **Check Safety** → *I am pregnant* → **Check** | "අවදානම් අවස්ථාවල warning එකක් එනවා. සෞඛ්‍ය පිළිතුරු database එකේ save වෙන්නේ නැහැ." |
| 6 | 1:15 | **Proceed to Checkout** → warning tick → Cash on Delivery → **Place Order** → order timeline → Mailpit → admin **Manage Orders** හි status **Confirmed** → timeline සහ දෙවන email | "දේශීය ගෙවීම් ක්‍රම, order tracking සහ email." |
| 7 | 0:45 | Phone camera එකෙන් labels දෙක scan කරන්න | "Genuine / Recalled, batch විස්තර සමඟ." |
| 8 | 1:00 | **Admin Dashboard** → chart, forecast, low stock, expiring batches | "Report වලින් එහාට, තීරණ ගන්න උදව් වෙනවා." |

## 3. අවංකව කිව යුතු දේ

Examiner කෙනෙක් අහන්න පෙර අපිම කීම හොඳයි.

- Herb විස්තර සහ safety warnings **නියැදි පෙළ** (`DEMO TEXT`, `SAMPLE`). Practitioner review එක තවම නැත්නම් "reviewed" කියා **නොකියන්න**. Sinhala review එකටත් එසේමය.
- Orders, customers, sales සහ forecast **demo data** මත පදනම් වේ.
- Recommender evaluation ([recommender_eval.md](../ml/reports/recommender_eval.md)): popularity baseline precision@5 **0.027**, recall@5 **0.136**; hybrid **0.083**, **0.417**. මේවා **synthetic data** වලින් ලැබුණු අගයන්; සැබෑ customers සඳහා ක්‍රියාකාරිත්වය ඔප්පු නොකරයි. එක් hidden product එකක් සඳහා precision@5 උපරිමය 0.200 කි.
- **PayHere** තේරීම පෙනෙන්නේ [.env](../.env) හි keys තියෙනවා නම් පමණි (sandbox: සැබෑ මුදල් අය නොවේ). `localhost` එකේදී ගෙවීමෙන් පසුවත් order එක **paid** නොවේ, ඒ නිසා demo step 6 සඳහා **Cash on Delivery** තෝරන්න. Card (Stripe) configure කර නැති නිසා නොපෙනේ.
- Singlish පිළිතුරු වල භාෂාව අස්වාභාවික විය හැක. සිංහල හෝ ඉංග්‍රීසි ප්‍රශ්න අසන්න.

## 4. යමක් වැරදුණොත්

| ගැටලුව | කරන්න |
|---|---|
| Assistant "I cannot write a full answer" කියයි | එය fallback එකයි: "AI නැති වුණත් site එක අදාළ items පෙන්වනවා" කියා ඉදිරියට යන්න. පැයක ප්‍රශ්න සීමාවක් ඇති නිසා කලින් වැඩිය නොඅසන්න. |
| Forecast ක්‍රමය වෙනස් ලෙස පෙනේ | `ml` service එක නැති විට PHP fallback එක වැඩ කරයි. `docker compose up -d --wait` |
| Phone එකට page එක load නොවේ | එකම Wi-Fi ද? `pwsh bin/set_lan_url.ps1` ධාවනය කර අලුත් labels ගන්න. විකල්පය: PC browser එකේ verify link එක open කරන්න. |
| Docker නතර වී ඇත | Docker Desktop open කර `docker compose up -d --wait` |

## 5. Rehearsal එකකට පසු

- Test order එක cancel කරන්න (admin → **Manage Orders** → **Cancelled**), stock ආපසු එකතු වේ.
- Cart safety පිළිතුරු **Clear my answers** කරන්න.
- Expired පෙන්වීමට හැදූ batch එක තියාගත හැක.
- Demo password සහ default admin password සැබෑ site එකක **භාවිත නොකරන්න**.
