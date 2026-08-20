# SmileCare — Dental Clinic Management System (UI/UX)

Prototype UI/UX oo dhammaystiran oo loogu talagalay **hal clinic ilkaha ah**.
Waxaa isticmaala **shaqaalaha** (reception, kaaliye, cashier) iyo **dhakhtarka (Dr)** —
ma jiro bog bukaan (patient portal) iyo multi-clinic midna.

Waa HTML / CSS / JavaScript saafi ah — build tool ma loo baahna.

---

## Sida loo furo

Fur `index.html` browser-ka (ama `python3 -m http.server` kadibna booqo `localhost:8000`).

Ma jiro server ama database — xogtu waa demo ku jirta `assets/js/data.js`,
waxna ka beddelidda waxay ku sii jirtaa inta aad bogga ku jirto.

---

## Doorarka (role-based access)

| Qaybta | Dhakhtar (Dr) | Shaqaale |
|---|:---:|:---:|
| Dashboard | ✅ | ✅ |
| Ballamaha (appointments) | ✅ | ✅ |
| Bukaanka + odontogram | ✅ | ✅ |
| Xusuusqorka caafimaad | ✅ wax ka beddel | 👁 akhris kaliya |
| Daaweynta (treatment plans) | ✅ abuur | 👁 akhris kaliya |
| Lacagta (faturado & lacag-qaadis) | ✅ | ✅ |
| Bakhaarka | ✅ | ✅ |
| Shaqaalaha | ✅ | 🚫 |
| Warbixin / falanqayn | ✅ | 🚫 |
| Settings-ka clinic-ka | ✅ wax ka beddel | 👁 akhris kaliya |

Doorka waxaa la doortaa bogga login-ka, waxaana lagu kaydiyaa `localStorage`.
`Settings → Beddel doorka` ayaa kuu ogolaanaya inaad si degdeg ah u tijaabiso labada door.

---

## Boggaga

| Route | Waxa uu qabto |
|---|---|
| `#/dashboard` | KPI-yada maalinta, safka bukaanka, dakhliga usbuuca, noocyada adeegga, deynta furan, dhaqdhaqaaqa, digniinta bakhaarka |
| `#/appointments` | Jadwal maalinle, shaandhayn xaalad, ballan cusub, wareejinta xaaladda (Confirmed → Waiting → In chair → Completed) |
| `#/patients` | Liiska bukaanka, raadin, diiwaangelin cusub |
| `#/patient/:id` | Diiwaanka buuxa: odontogram, daaweyn, ballamo, faturado, xusuusqor |
| `#/treatments` | Qorshaha daaweynta + qiimaha rasmiga ah ee adeegyada |
| `#/billing` | Faturado, hadhaaga, lacag-qaadis (Cash / EVC Plus / Card) |
| `#/inventory` | Agabka clinic-ka + digniin marka la gaadho xadka ugu yar |
| `#/staff` | Kooxda clinic-ka (Dr kaliya) |
| `#/reports` | Dakhli, adeegyada ugu dakhliga badan, waxqabadka dhakhaatiirta (Dr kaliya) |
| `#/settings` | Macluumaadka clinic-ka, doorka, muuqaalka |

---

## Odontogram

Shaxda ilkaha waxay isticmaashaa **nidaamka FDI** (32 ilig oo qaan-gaadh ah: 18–11, 21–28, 48–41, 31–38).
Gujinta iligga ayaa ku wareejinaysa xaaladaha:

`Caafimaad → Caries → Filling → Crown → La daaweeyay → Maqan → Caafimaad`

Midab kasta wuxuu leeyahay legend hoosta ku qoran.

---

## Design system

Waxaa lagu dhisay CSS custom properties (`assets/css/style.css`):

- **Midabka aasaasiga ah** — teal `#0EA5A4` (dhaqan caafimaad, nadiif)
- **Font** — Inter (qoraalka), Poppins (cinwaannada)
- **Dark mode** — token-yada oo dhan waa la beddelaa; waxaa lagu kaydiyaa `localStorage`
- **Responsive** — sidebar collapse (≥992px), drawer + scrim (<992px), shaxdu waxay leedahay `overflow-x` gaar ah si aan boggu u jiidmin
- **Qaybaha** — `.card`, `.kpi`, `.badge`, `.btn`, `.toolbar`, `.segmented`, `.timeline`, `.queue`, `.meter`, `.modal`, `.toast`

---

## Faylasha

```
index.html            login (dooro Dr / Shaqaale)
app.html              shell-ka: sidebar + topbar + view container
assets/css/style.css  design system + dhammaan qaybaha
assets/js/data.js     xogta demo-ga (bukaan, ballamo, faturado, agab, shaqaale)
assets/js/app.js      hash router, render-ka boggaga, foomamka, xaaladda
```

## Waxa xiga (haddii backend la doonayo)

`assets/js/data.js` ayaa ah meesha kaliya ee xogtu ku jirto — beddelidda `state`
ee `app.js` inay `fetch()` isticmaasho waa waxa kaliya ee loo baahan yahay
si loogu xidho API dhab ah. Sidoo kale waxaa lagama maarmaan ah:
xaqiijin isticmaale (auth) dhab ah, audit log diiwaanka caafimaad, iyo
kaydinta X-ray sawirrada.
