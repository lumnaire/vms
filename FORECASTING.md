# Paano Gumagana ang Forecast (ARIMA)

### Gabay para sa Supervisor, Staff, at sa mga Hindi Teknikal

> **Para kanino ito?** Para sa sinumang gumagamit ng pahinang **Forecasts** ng
> Virac Public Market System at gustong maintindihan kung **saan galing** ang
> mga numerong nakikita nila, **paano ito kinukuwenta**, at **kailan ito
> mapagkakatiwalaan**. Hindi kailangang marunong mag-program para maintindihan
> ang unang bahagi. Ang mga pormula ay nasa huling bahagi, ang
> [Teknikal na Apendiks](#teknikal-na-apendiks), para sa gustong mag-check
> gamit ang calculator.

---

## Nilalaman

1. [Sa madaling salita](#1-sa-madaling-salita)
2. [Saan kinukuha ang datos](#2-saan-kinukuha-ang-datos)
3. [Ang dalawang sinusukat: Presyo at Suplay](#3-ang-dalawang-sinusukat-presyo-at-suplay)
4. [Ano ang ARIMA? (Paliwanag na walang math)](#4-ano-ang-arima-paliwanag-na-walang-math)
5. [Ang mga hakbang, isa-isa](#5-ang-mga-hakbang-isa-isa)
6. [Halimbawa 1: Forecast ng Presyo](#6-halimbawa-1-forecast-ng-presyo)
7. [Halimbawa 2: Forecast ng Suplay](#7-halimbawa-2-forecast-ng-suplay)
8. [Ang "saklaw" o range: bakit hindi iisang numero lang](#8-ang-saklaw-o-range-bakit-hindi-iisang-numero-lang)
9. [Ang trend: Pataas, Pababa, o Stable](#9-ang-trend-pataas-pababa-o-stable)
10. [Paano basahin ang pahina ng Forecasts](#10-paano-basahin-ang-pahina-ng-forecasts)
11. [Kailan walang forecast](#11-kailan-walang-forecast)
12. [Kailan ina-update ang forecast](#12-kailan-ina-update-ang-forecast)
13. [Gaano ito katumpak?](#13-gaano-ito-katumpak)
14. [Mga limitasyon na dapat tandaan](#14-mga-limitasyon-na-dapat-tandaan)
15. [Demo data para makita ang forecast](#15-demo-data-para-makita-ang-forecast)
16. [Teknikal na Apendiks](#teknikal-na-apendiks)
17. [Talahuluganan (Glosaryo)](#talahuluganan-glosaryo)

---

## 1. Sa madaling salita

Tinitingnan ng sistema ang **nakaraang 30 araw** ng bawat isda: magkano ang
presyo at ilang kilo ang dumating sa palengke **araw-araw**. Mula roon,
hinuhulaan nito ang **susunod na 3 araw**.

Dalawang bagay ang hinuhulaan para sa bawat isda at klase:

| Hinuhulaan  | Tanong na sinasagot                                     | Yunit  |
| ----------- | ------------------------------------------------------- | ------ |
| **Presyo**  | "Magkano kaya ang isang kilo bukas at sa makalawa?"     | ₱ / kg |
| **Suplay**  | "Ilang kilo kaya ang darating sa palengke?"             | kg     |

Ang bawat hula ay may kasamang **saklaw** (halimbawa: "₱111 hanggang ₱114")
at isang **trend** (Pataas, Pababa, o Stable).

Ang paraan ng paghula ay tinatawag na **ARIMA**, isang kilalang paraan sa
estadistika para hulaan ang mga numerong nagbabago araw-araw.

---

## 2. Saan kinukuha ang datos

Ang lahat ng datos ay galing sa mga **batch** na isinusumite ng mga vendor sa
**My Inventory**. Pero hindi lahat ng batch ay ginagamit. Kailangang:

| Kondisyon                                   | Bakit                                                                                 |
| ------------------------------------------- | ------------------------------------------------------------------------------------- |
| ✅ **Kumpirmado ng staff** (confirmed)       | Ang presyong hindi pa aprobado ay hindi pa opisyal. Hindi ito dapat makaapekto sa hula. |
| ✅ **Bago ang araw na ito** (hindi kasama ngayon) | Hindi pa tapos ang araw ngayon. Puwede pang dumating ang ibang batch mamaya.          |
| ✅ **Nasa loob ng huling 30 araw**            | Sapat na ito para makita ang takbo, at hindi na masyadong luma.                         |

> **Mahalaga:** Ang *Release* (ang naitalang nabenta ng vendor) ay **hindi**
> ginagamit sa forecast. Ang hinuhulaan ay ang **dumating** na isda (suplay) at
> ang **presyo**, hindi ang benta.

---

## 3. Ang dalawang sinusukat: Presyo at Suplay

Bawat araw, pinagsasama-sama ang lahat ng kumpirmadong batch ng isang isda (at
klase) para maging **isang numero** lang bawat araw.

### Ang Presyo ay **inaaverage**

Ang presyo ay *halaga bawat kilo*. Walang saysay na pagsamahin ang
₱180 + ₱190 + ₱200 = ₱570. Walang isda na ₱570 ang kilo! Kaya kinukuha ang
**average**: ang karaniwang presyo sa palengke noong araw na iyon.

### Ang Suplay ay **pinagsasama (sum)**

Ang suplay ay *dami ng isda*. Kung 12 kg ang dala ni A, 6 kg pa sa pangalawang
batch niya, 8 kg kay B, at 5 kg kay C, **31 kg** ang kabuuang dumating.
Pagsasama lang ang tamang paraan.

### Halimbawa ng isang araw (Bangus, Second Class)

| Vendor | Batch   | Presyo / kg | Stock (kg) |
| ------ | ------- | ----------: | ---------: |
| A      | Batch 1 |     ₱180.00 |         12 |
| A      | Batch 2 |     ₱170.00 |          6 |
| B      | Batch 1 |     ₱190.00 |          8 |
| C      | Batch 1 |     ₱200.00 |          5 |

| Sukatan                 | Kuwenta                               | Resulta     |
| ----------------------- | ------------------------------------- | ----------- |
| **Presyo** ng araw      | (180 + 170 + 190 + 200) ÷ 4           | **₱185.00** |
| **Suplay** ng araw      | 12 + 6 + 8 + 5                        | **31 kg**   |

> **Paalala:** Bawat batch ay may **iisang boto** sa average, gaano man ito
> kabigat. Ang 5 kg na batch ay kasimbigat ng 12 kg na batch sa pag-average.
> Tingnan ang [Mga Limitasyon](#14-mga-limitasyon-na-dapat-tandaan).

Kapag ginawa ito sa bawat araw, magkakaroon ng **listahan ng numero**, isa bawat
araw, mula pinakaluma hanggang pinakabago. Ito ang tinatawag na **time series**,
at ito ang "pinag-aaralan" ng ARIMA.

---

## 4. Ano ang ARIMA? (Paliwanag na walang math)

Isipin mo na nagmamaneho ka sa kalsadang paakyat. Hindi mo kailangang makita
ang dulo ng kalsada para hulaan kung nasaan ka pagkalipas ng ilang segundo.
Sapat nang alam mo kung **gaano ka kabilis** at **kung bumibilis ka ba o
bumabagal**. Ganyan din ang ARIMA. Hindi nito tinitingnan ang presyo mismo,
kundi **kung paano nagbabago** ang presyo araw-araw.

Ang **ARIMA(1,1,1)** ay may tatlong bahagi. Bawat isa ay may simpleng ibig sabihin:

| Bahagi | Buong pangalan                     | Ibig sabihin sa simpleng salita |
| ------ | ---------------------------------- | -------------------------------- |
| **AR** | *AutoRegressive* (1)                | "**Ugali ng pagbabago.**" Kung tumaas kahapon, tataas din ba ngayon? O kabaligtaran: kung tumaas kahapon, babawi ba pababa ngayon? Natututunan ito mula sa nakaraan. |
| **I**  | *Integrated* (1)                    | "**Pagbabago, hindi ang mismong presyo.**" Sa halip na ₱200, ₱203, ₱201, ang tinitingnan ay +3, −2. Mas madaling hulaan ang galaw kaysa sa mismong halaga. |
| **MA** | *Moving Average* (1)                | "**Epekto ng sorpresa.**" Kung may hindi inaasahang nangyari kahapon (biglang taas o baba), may kaunting epekto pa ito bukas, pero agad ding nawawala. |

Ang tatlong **(1)** ay nangangahulugang **isang araw lang pabalik** ang
tinitingnan ng bawat bahagi. Kaya simple at mabilis ang modelo, at bagay ito sa
palengke kung saan mabilis magbago ang sitwasyon.

---

## 5. Ang mga hakbang, isa-isa

Ganito ang ginagawa ng sistema para sa **bawat isda, bawat klase, at bawat
sukatan** (presyo at suplay):

| #   | Hakbang                    | Ano ang nangyayari |
| --- | -------------------------- | ------------------- |
| 1   | **Kunin ang kasaysayan**   | Sa loob ng huling 30 calendar days; kailangan ng 7 consecutive recorded days hanggang sa latest actual record. |
| 2   | **Kunin ang pagbabago**    | Ibawas ang kahapon sa ngayon para sa bawat araw. Halimbawa: 100 → 101 → 103 ay nagiging **+1, +2**. |
| 3   | **Fitted drift (μ)**      | Tinatantiya kasama ng φ at θ gamit ang maximum likelihood; maaaring iba sa simpleng average ng changes. |
| 4   | **Ugali ng pagbabago (φ)** | Gaano kalakas sumusunod ang pagbabago ngayon sa pagbabago kahapon? Ang **positibo** ay "tuloy-tuloy." Ang **negatibo** ay "pabalik-balik." Ang **malapit sa 0** ay "walang kinalaman." |
| 5   | **Epekto ng sorpresa (θ)** | Gaano katagal nananatili ang epekto ng hindi inaasahang galaw? |
| 6   | **Karaniwang mintis (σ)**  | Kapag ginamit ang modelo sa nakaraan, gaano kalayo ang hula sa totoo, sa karaniwan? Ito ang ginagamit para sa **saklaw**. |
| 7   | **Hulaan ang 3 araw**      | Hulaan ang Araw 1. Gamitin ang hula sa Araw 1 bilang "kahapon" ng Araw 2, at gayundin sa Araw 3. Magkakadugtong ang tatlong araw. |
| 8   | **Lagyan ng saklaw at trend** | Bawat araw ay may saklaw na lumalapad habang lumalayo. Ang buong 3 araw ay may iisang trend: Pataas, Pababa, o Stable. |

---

## 6. Halimbawa 1: Forecast ng Presyo

Ang updated na detalyadong halimbawa ay nasa **`/calculation`**, sa seksyong
“Sample computation: Pusit · Special Class.” Kinukuwenta ito mismo ng bagong
forecasting service, kaya tugma ang coefficients, forecasts at prediction intervals.
Ang dating halimbawa gamit ang autocorrelation shortcut ay pinalitan na.

## 7. Halimbawa 2: Forecast ng Suplay

Sa `/calculation`, piliin ang **Supply** at ilagay ang magkakasunod na daily totals
sa kilograms. Hiwalay ang modelong ito sa presyo. Ang aktuwal na production history
ay dapat may magkakasunod na recorded days na nagtatapos kahapon; hindi nito
ginagawang tunay na zero delivery ang isang araw na walang record.

## 8. Ang "saklaw" o range: bakit hindi iisang numero lang

Ginagamit ang **model-based forecast variance Vₕ**, kasama ang AR/MA effects:
raw forecast ± 1.96 × √Vₕ. Hindi na ginagamit ang dating σ√h shortcut.
Ang intervals ay conditional sa fitted parameters at hindi kasama ang uncertainty
sa parameter estimation. Pinuputol sa zero ang negatibong published values at bounds.
Kailangan pa ring sukatin ang aktuwal na coverage nito sa market data.

## 9. Ang trend: Pataas, Pababa, o Stable

Ang trend ay sumasagot sa tanong: ***"Papunta ba ito sa mas mataas o mas mababa
kaysa sa nakita natin nitong nakaraang linggo?"***

Ikinukumpara ang **hula sa huling araw (Araw 3)** sa **average ng huling 7
totoong araw**:

| Kung ang hula sa Araw 3 ay…                            | Trend         |
| ------------------------------------------------------ | ------------- |
| mahigit **2% na mas mataas** sa average ng 7 araw      | 🟢 **Pataas** (upward)   |
| mahigit **2% na mas mababa** sa average ng 7 araw      | 🔴 **Pababa** (downward) |
| nasa loob ng **±2%**                                    | ⚪ **Stable**            |

**Halimbawa:** Kung ang average ng huling 7 araw ay ₱200:

- Hula sa Araw 3 = ₱206 → +3% → **Pataas**
- Hula sa Araw 3 = ₱197 → −1.5% → **Stable**
- Hula sa Araw 3 = ₱190 → −5% → **Pababa**

> Bakit 7 araw? Isang buong linggo ito, kaya kasama ang mga araw ng
> karaniwang linggo at weekend, at hindi lang ang galaw ng isang araw ang
> pinagbabatayan.

---

## 10. Paano basahin ang pahina ng Forecasts

Buksan ang **Forecasts** sa menu ng Supervisor.

### Mga filter sa itaas

| Filter            | Gamit |
| ----------------- | ----- |
| **Fish Type**     | Piliin ang isda. Nasa **itaas ng listahan** ang mga isdang may forecast. Ang may nakasulat na **"· not enough data"** ay wala pang sapat na kasaysayan (tingnan ang [Seksyon 11](#11-kailan-walang-forecast)). |
| **Quality Class** | Kusang napipili ayon sa isda. |
| **Metric**        | **Price** (presyo) o **Supply** (suplay). |

### Ang mga card

| Card               | Ibig sabihin |
| ------------------ | ------------ |
| **3-Day Trend**    | Pataas, Pababa, o Stable (Seksyon 9), kasama ang 7-day average na pinagkumparahan. |
| **Avg Forecast**   | Average ng tatlong hula, kasama ang mga petsang sakop. |
| **Lowest in Period / Highest in Period** | Ang pinakamababa at pinakamataas **na hula sa 3 araw**, at **kung aling araw** iyon (hal. "Day 2 · Thu, Oct 22"). Galing ito sa mismong hula, **hindi** sa saklaw (CI). Kapag pare-pareho ang tatlong araw, "Same on all 3 days" ang nakasulat. |

> Iisang kuwenta ang pinagmumulan ng mga card, ng graph at ng talaan. Ang araw
> na nakasulat sa *Highest* ay siya ring berdeng tuldok sa graph at may
> **Highest** na badge sa talaan; ganoon din ang *Lowest* (pulang tuldok).

### Ang graph

| Makikita sa graph       | Ibig sabihin |
| ----------------------- | ------------ |
| **Historical** (linya)  | Ang **totoong** presyo o suplay sa nakaraang 30 araw. |
| **Forecast** (linya)    | Ang **hula** para sa susunod na 3 araw. |
| **Mapusyaw na asul na bahagi** | Ang **saklaw** (Lower CI hanggang Upper CI). |
| **"Today"**             | Ang hangganan sa pagitan ng nakaraan at ng hula. |
| **Berde / pulang tuldok** | Ang pinakamataas / pinakamababang araw ng hula. |
| **Dilaw na putol-putol na linya** | Ang Avg Forecast, sa ibabaw ng tatlong araw ng hula. |
| **Lilang putol-putol na linya** | Ang **tariff** (opisyal na presyo) ng isda. Sa Price lang ito lumalabas. |
| **Puting tuldok sa nakaraan** | Ang **dating hula** para sa araw na iyon, para makita kung gaano kalapit sa totoo. |

### Ang talaan sa ibaba (Forecast Breakdown)

| Kolum          | Ibig sabihin |
| -------------- | ------------ |
| **Day / Date** | Aling araw ang hinuhulaan. |
| **Predicted**  | Ang hula. |
| **Lower CI / Upper CI** | Pinakamababa at pinakamataas sa saklaw. |
| **Δ Change**   | Pagbabago mula sa naunang araw. Sa Day 1, ikinukumpara ito sa huling totoong araw. |
| **vs Tariff**  | (Price lang) Gaano kalayo ang hula sa tariff. |
| **Trend**      | Ang trend ng buong 3 araw. |

### Past Forecasts vs Actual

Sa ilalim ng talaan, makikita ang **mga dating hula** para sa mga araw na
lumipas na, katabi ng totoong nangyari at ng **mintis (error %)**. Ang "Avg
error" ay ang karaniwang mintis sa mga araw na may totoong batch.

---

## 11. Kailan walang forecast

Kailangan ng ARIMA ng **hindi bababa sa 7 magkakasunod na recorded days hanggang sa latest actual record** na may kumpirmadong batch ng
isang isda (sa loob ng huling 30 araw). Ang bilang na 7 ay operational minimum, hindi patunay na statistically sufficient ang sample. Kung kulang:

- Hindi gagawa ng hula ang sistema. **Sinasadya ito.** Mas mabuting walang hula
  kaysa sa hulang galing sa hula-hula.
- Sa listahan ng isda, makikita ang **"no confirmed history"** o bilang tulad ng **"2/7 consecutive days"**. Ang may sapat na records pero wala pang saved run ay **"ready to forecast"**.
- Sa graph, may mensaheng nagsasabing kailangan pa ng kasaysayan.

Normal na **maraming isda ang walang forecast**. May 125 na uri ng isda sa
system, pero iilan lang ang regular na ibinebenta. Kapag nagsimula nang
magbenta ang mga vendor ng isang isda araw-araw, lalabas ang forecast nito
pagkalipas ng isang linggo.

---

## 12. Kailan ina-update ang forecast

Sa hosting, maaaring patakbuhin ang `php artisan forecast:status` para makita ang
server date, daily history at bilang ng forecasts mula sa kasalukuyang model.
Read-only ito. Kapag may eligible history pero wala pang bagong forecasts, gamitin
ang `php artisan forecast:generate`. Hindi nito pinupunan ang nawawalang inventory records.

| Kailan                         | Ano ang nangyayari |
| ------------------------------ | ------------------ |
| **Araw-araw, 12:01 ng hatinggabi** | Awtomatikong gumagawa ng bagong forecast ang system para sa lahat ng isdang may sapat na datos (kailangang tumatakbo ang *scheduler* sa server). |
| **Kapag binuksan ang pahina at wala pang forecast ngayong araw** | Gumagawa agad ang pahina ng forecast para sa napiling isda. Kaya kahit hindi tumakbo ang scheduler, laging bukas hanggang sa ikatlong araw ang ipinapakita. |
| **Mano-mano**                  | Puwedeng patakbuhin ng developer ang `php artisan forecast:generate`. |

> Dahil hindi kasama ang araw ngayon (Seksyon 2), **hindi magbabago ang
> forecast sa buong araw** kahit may bagong batch na makumpirma. Papasok ang
> mga iyon sa forecast bukas.

**Hindi binubura ang mga lumang forecast.** Bawat araw na pagtakbo ay
itinatabi, kaya may kasaysayan ng mga hula para sa report at para sa muling
pag-train ng modelo. Kapag pinatakbo ulit sa parehong araw, iyon lang araw na
iyon ang napapalitan. Ang pinakabagong takbo ang ipinapakita sa pahina.

Para sa datos na pang-train sa labas ng system, may **History (CSV)** sa
Reports ng supervisor: araw-araw na suplay, benta, pull-out, presyo at tariff
ng bawat isda.

---

## 13. Gaano ito katumpak?

Sinubukan namin ang modelo gamit ang **backtest**: itinago namin ang ilang
totoong araw, pinahula ang system, at ikinumpara ang hula sa totoong nangyari.
Ginawa ito nang paulit-ulit sa huling 30 araw ng 6 na isda sa demo data.

Ikinumpara rin ito sa pinakasimpleng hula: ***"pareho lang ng kahapon."***

| Sukatan   | Karaniwang mintis ng ARIMA | Karaniwang mintis ng "pareho lang ng kahapon" | Totoong halaga na pumasok sa saklaw |
| --------- | -------------------------: | ---------------------------------------------: | ----------------------------------: |
| Presyo    | **1.9%**                   | 2.0%                                           | **97%**                             |
| Suplay    | **7.8%**                   | 8.6%                                           | **97%**                             |

**Paano ito basahin:**

- Sa presyo, karaniwang **mga ₱4 lang ang layo** ng hula sa isang ₱200 na isda.
- Ang suplay ay mas mahirap hulaan. Nakadepende ito sa huli ng mangingisda,
  sa panahon, at sa dagat.
- Halos **95%** ang pumasok sa saklaw, gaya ng ipinangako ng 95% na saklaw.
  Tapat ang saklaw.
- Bahagyang mas mahusay ang ARIMA kaysa sa "pareho lang ng kahapon." Normal ito
  sa 1–3 araw na hula. Ang **pangunahing dagdag-halaga ng ARIMA** ay ang
  **saklaw** at ang **trend**, na wala sa simpleng hula.

> ⚠️ **Nakadepende ang katumpakan sa kalidad ng datos.** Sa mga lumang demo
> data (galing sa `VendorInventorySeeder`), ang presyo ay tumatalon-talon nang
> parang lagari at walang totoong ugali. Doon, mga **6%** ang mintis sa presyo
> at **43%** sa suplay, at napakalapad ng saklaw. Hindi iyon sira ang modelo.
> Walang pattern na matututunan sa ganoong datos.

---

## 14. Mga limitasyon na dapat tandaan

| Limitasyon | Paliwanag | Halimbawa |
| ---------- | --------- | --------- |
| **Hindi nito alam ang "weekend effect."** | Ang ARIMA(1,1,1) ay tumitingin lang ng isang araw pabalik. Hindi nito alam na tuwing Sabado at Linggo ay mas mahal o mas marami ang isda. | Kung Linggo ang huling araw at mataas ang presyo dahil weekend, dadalhin nito ang mataas na presyo sa Martes at Miyerkules, at maaaring sabihing **"Pataas"** kahit babalik naman sa normal. |
| **Hindi nito nakikita ang mga biglaang pangyayari.** | Bagyo, pagbabawal mangisda, pista, at iba pa. | Kapag may bagyo bukas, hindi ito alam ng modelo hangga't hindi pa ito lumalabas sa datos. |
| **Pantay ang boto ng bawat batch sa presyo.** | Ang average ng presyo ay hindi tinitimbang ayon sa kilo. | Ang 2 kg na batch na ₱300 at ang 50 kg na batch na ₱200 ay parehong may iisang boto. Ang average ay ₱250, kahit halos lahat ng isda ay nabili sa ₱200. |
| **Nilalaktawan ang araw na walang benta.** | Kung walang batch ng isang isda sa isang araw, hindi iyon binibilang na "0". Nilalaktawan lang. | Kung may datos noong Lunes at Miyerkules pero wala noong Martes, ituturing ng modelo na magkasunod na araw ang Lunes at Miyerkules. |
| **3 araw lang ang hula.** | Lumalabo nang husto ang hula pagkalipas ng ilang araw. | Huwag gamitin ang forecast sa pagpaplano ng susunod na buwan. |
| **Hindi ito "presyong dapat sundin."** | Ito ay hula batay sa nakaraan, hindi utos. | Ang **Price Guide** pa rin ang opisyal na batayan ng tamang presyo. |

---

## 15. Demo data para makita ang forecast

Para sa panel demonstration, gamitin ang **`ForecastPanelDemoSeeder`**.
Gumagawa ito ng **6 na hiwalay na [DEMO] fish**: Hipon, Pusit, Bangus (medium),
Tilapia, Tangigue (natural), at Galunggong (saday payo). May pataas, pababa,
stable at mas maingay na simulated price at supply patterns.

```bash
php artisan db:seed --class=ForecastPanelDemoSeeder
```

- Sa default settings, may **40 araw ng synthetic history**, 3 demo vendors,
  **720 batches**, at **36 current forecast points**. Ang production model pa rin
  ang nag-eestimate ng coefficients at gumagawa ng predictions gamit ang 30-day window.
- May **7 araw ng retrospective comparisons** para makita ang RMSE, MAE at MAPE.
  Simulations ang mga ito, hindi forecasts na totoong ginawa noong nakaraang linggo.
- Nananatili ang existing researcher data. Kapag inulit ang seeder, sarili nitong
  marked mock records lang ang nire-refresh; walang duplicate na same-day batch.
- Inactive at malinaw ang pangalan ng demo accounts. Sarado na ang mock historical
  stock gamit ang marked pull-out records, kaya walang ipinapakitang available mock stock sa public board.
- Alias na lang ang lumang `ForecastDemoSeeder` papunta sa bagong safe implementation.

Pagkatapos, buksan ang **Forecasts** at pumili ng **[DEMO]** na isda. May banner
na nagsasabing synthetic ang data at retrospective ang comparisons.
Ipinapakita nito ang software functionality. Kailangan pa rin ng real-market
validation at operational checks bago masabing handa para sa actual deployment.

---

## Teknikal na Apendiks

Para sa gustong mag-check gamit ang calculator o spreadsheet. Ang lahat ng ito
ay nasa `app/Services/ArimaService.php`, at ang mga setting ay nasa
`config/forecast.php`.

### A. Ang datos bawat araw

Para sa isda *f*, klase *k*, at araw *t*, gamit ang lahat ng kumpirmadong batch
*b* sa araw na iyon:

```
Presyo:  y[t] = (1 / Nₜ) · Σ price_per_kgᵦ        (average ng Nₜ na batch)
Suplay:  y[t] = Σ stock_kgᵦ                        (kabuuang kilo)
```

Ang latest consecutive recorded segment hanggang sa huling actual record ang kasama, sa loob ng `ngayon − 30` hanggang
`kahapon`. Kailangan ng `n ≥ 7` na araw.

### B. Unang pagkakaiba (d = 1)

```
Δy[t] = y[t] − y[t−1]
```

### C. Standard maximum-likelihood estimation

Ang μ, φ at θ ay fitted gamit ang **exact Gaussian maximum likelihood**.
Ang φ at θ ay sabay na ino-optimize; hindi sila kinukuha direkta sa autocorrelation.
Ang μ ay fitted drift at maaaring iba sa simpleng average ng daily changes.

Batayan: Mardesci & Fitriani (2025), section 2.1, p. 77:
https://doi.org/10.64570/agrivolution.v1i2.33
Annual oil-palm data ang kanilang pag-aaral; ina-adapt ang method sa daily fish data.
PHP ang implementation; hindi kailangan ng Python sa production hosting.

### D. Innovations at variance

Kalman innovations v[t] at unit-variance factors F[t] ang ginagamit:

```text
σ² = Σ(v[t]² / F[t]) / m
```

Ang mga ito ay galing sa jointly fitted model na may stationary initialization.
Kapag constant ang lahat ng changes, deterministic limiting case ito: zero ang
innovation variance at walang finite Gaussian likelihood na iri-report.

### E. Paghula at target dates

```text
predicted change = μ + φ(previous change − μ) + θ(previous filtered shock)
raw next level = previous raw level + predicted change
```

Expected future shocks ay zero. Mula sa tunay na petsa ng latest observation,
kinukuwenta ang bawat unobserved day hanggang ngayon bago i-publish ang bukas at ang
kasunod na mga araw. Halimbawa, kung November 17 ang latest record at November 24
ngayon, ang November 25 forecast ay model step 8. Kasama ang lahat ng steps sa variance.
Raw unrounded levels ang ginagamit sa recursion; walang idinadagdag na inventory records.

### F. Prediction interval

```text
half-width = 1.96 × √(model forecast variance)
published prediction and bounds = max(0, raw result), rounded to 2 decimals
```

Kasama sa variance ang integrated ARMA impulse responses at filtered-state uncertainty.
Hindi kasama ang uncertainty sa estimated coefficients. Ang bilang na 7 observations
ay processing minimum lamang, hindi patunay na sapat ang sample sa research.

### I. Trend

```
baseline = average ng huling 7 totoong y[t]

ŷ[T+3] > baseline × 1.02   →  upward   (Pataas)
ŷ[T+3] < baseline × 0.98   →  downward (Pababa)
iba pa                     →  stable
```

### J. Mga setting (`config/forecast.php`)

| Setting               | `.env`                        | Default | Gamit |
| --------------------- | ----------------------------- | ------- | ----- |
| `horizon`             | `FORECAST_HORIZON`            | `3`     | Ilang araw ang huhulaan |
| `min_history`         | `FORECAST_MIN_HISTORY`        | `7`     | Pinakakaunting araw ng datos bago humula |
| `history_days`        | `FORECAST_HISTORY_DAYS`       | `30`    | Ilang araw ang ginagamit sa pag-aaral ng modelo |
| `history_chart_days`  | `FORECAST_HISTORY_CHART_DAYS` | `30`    | Ilang araw ng kasaysayan ang ipinapakita sa graph |
| `order`               | —                             | `1,1,1` | Ang (p, d, q) ng ARIMA |
| `z`                   | —                             | `1.96`  | Lapad ng saklaw (1.96 = 95%) |
| `trend_threshold`     | —                             | `0.02`  | Ang ±2% na hangganan ng trend |
| `trend_baseline_days` | —                             | `7`     | Ilang totoong araw ang average na batayan ng trend |

### K. Saan nakaimbak

Bawat hula ay nasa table na `forecasts`, kasama ang lahat ng natutunang halaga
(φ, θ, μ, σ, at ang trend baseline) sa kolum na `arima_params`:

```sql
SELECT f.name, fc.quality_class, fc.metric, fc.forecast_date,
       fc.predicted_value, fc.predicted_min, fc.predicted_max,
       fc.trend, fc.arima_params
FROM   forecasts fc
JOIN   fish_types f ON f.id = fc.fish_type_id
ORDER  BY f.name, fc.metric, fc.forecast_date;
```

### L. Mga file

| File                                               | Laman |
| -------------------------------------------------- | ----- |
| `app/Services/ArimaService.php`                    | Ang makina ng ARIMA (lahat ng pormula sa itaas) |
| `app/Services/ArimaMaximumLikelihood.php`         | Standard exact Gaussian maximum-likelihood fitting |
| `app/Console/Commands/GenerateForecasts.php`       | `php artisan forecast:generate` |
| `app/Http/Controllers/Supervisor/ForecastController.php` | Ang pahina ng Forecasts |
| `database/seeders/ForecastSeeder.php`              | Gumagawa ng forecast para sa lahat ng isda |
| `database/seeders/ForecastPanelDemoSeeder.php`     | Clearly marked synthetic panel dataset at retrospective comparisons |
| `tests/Unit/ArimaServiceTest.php`                  | Mga test, kasama ang Halimbawa 1 |

---

## Talahuluganan (Glosaryo)

| Salita                  | Kahulugan |
| ----------------------- | --------- |
| **ARIMA**               | *AutoRegressive Integrated Moving Average*. Paraan ng paghula sa mga numerong nagbabago araw-araw. |
| **Forecast**            | Hula sa hinaharap batay sa nakaraan. |
| **Time series**         | Listahan ng numero na nakaayos ayon sa araw. |
| **Batch**               | Isang pagsumite ng vendor ng isang isda (Batch 1, Batch 2, …). |
| **Kumpirmado (confirmed)** | Inaprubahan ng staff. Ito lang ang ginagamit sa forecast. |
| **Suplay**              | Kabuuang kilo ng isdang dumating sa palengke. |
| **Pagkakaiba (difference)** | Ang pagbabago mula kahapon hanggang ngayon. |
| **φ (phi)**             | "Ugali ng pagbabago": gaano sumusunod ang pagbabago ngayon sa kahapon. |
| **θ (theta)**           | "Epekto ng sorpresa": gaano katagal tumatagal ang epekto ng hindi inaasahang galaw. |
| **μ (mu)**              | Fitted drift mula sa maximum-likelihood estimation; maaaring iba sa sample mean change. |
| **σ (sigma)**           | Fitted innovation standard deviation ng maximum-likelihood model. |
| **Residual**            | Ang bahaging hindi naipaliwanag ng modelo, ang "sorpresa." |
| **Saklaw / CI**         | *Confidence interval*. Ang pinakamababa at pinakamataas na makatwirang mangyari. |
| **95%**                 | Nominal coverage kung angkop ang fitted Gaussian model; kailangan pa ring i-check sa tunay na market data. |
| **Trend**               | Pataas, Pababa, o Stable, kumpara sa huling 7 araw. |
| **Backtest**            | Pagsubok sa modelo gamit ang lumang datos na alam na ang sagot. |
| **Seeder**              | Programang naglalagay ng sample na datos sa database. |
