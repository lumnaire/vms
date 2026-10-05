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

Tinitingnan ng sistema ang **nakaraang 90 araw** ng bawat isda: magkano ang
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
| ✅ **Nasa loob ng huling 90 araw**            | Sapat na ito para makita ang takbo, at hindi na masyadong luma.                         |

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
| 1   | **Kunin ang kasaysayan**   | Hanggang 90 araw ng presyo (average) o suplay (sum) bawat araw. Kung **kulang sa 7 araw**, hindi na itutuloy. |
| 2   | **Kunin ang pagbabago**    | Ibawas ang kahapon sa ngayon para sa bawat araw. Halimbawa: 100 → 101 → 103 ay nagiging **+1, +2**. |
| 3   | **Karaniwang galaw (μ)**   | Ang average ng lahat ng pagbabago. Halimbawa: "tumataas nang ₱1.11 bawat araw sa karaniwan." |
| 4   | **Ugali ng pagbabago (φ)** | Gaano kalakas sumusunod ang pagbabago ngayon sa pagbabago kahapon? Ang **positibo** ay "tuloy-tuloy." Ang **negatibo** ay "pabalik-balik." Ang **malapit sa 0** ay "walang kinalaman." |
| 5   | **Epekto ng sorpresa (θ)** | Gaano katagal nananatili ang epekto ng hindi inaasahang galaw? |
| 6   | **Karaniwang mintis (σ)**  | Kapag ginamit ang modelo sa nakaraan, gaano kalayo ang hula sa totoo, sa karaniwan? Ito ang ginagamit para sa **saklaw**. |
| 7   | **Hulaan ang 3 araw**      | Hulaan ang Araw 1. Gamitin ang hula sa Araw 1 bilang "kahapon" ng Araw 2, at gayundin sa Araw 3. Magkakadugtong ang tatlong araw. |
| 8   | **Lagyan ng saklaw at trend** | Bawat araw ay may saklaw na lumalapad habang lumalayo. Ang buong 3 araw ay may iisang trend: Pataas, Pababa, o Stable. |

---

## 6. Halimbawa 1: Forecast ng Presyo

Narito ang average na presyo ng isang isda sa loob ng **10 araw** (sa ₱/kg):

| Araw    |   1 |   2 |   3 |   4 |   5 |   6 |   7 |   8 |   9 |  10 |
| ------- | --: | --: | --: | --: | --: | --: | --: | --: | --: | --: |
| Presyo  | 100 | 101 | 103 | 102 | 105 | 104 | 108 | 107 | 111 | 110 |

Kita mo ang ugali? **Tumataas sa kabuuan, pero pabalik-balik.** Tataas nang
malaki, tapos bababa nang kaunti, tataas ulit.

### Hakbang 2: Ang pagbabago bawat araw

| Mula → Hanggang | 1→2 | 2→3 | 3→4 | 4→5 | 5→6 | 6→7 | 7→8 | 8→9 | 9→10 |
| --------------- | --: | --: | --: | --: | --: | --: | --: | --: | ---: |
| Pagbabago (₱)   |  +1 |  +2 |  −1 |  +3 |  −1 |  +4 |  −1 |  +4 |   −1 |

### Hakbang 3 hanggang 6: Ang natutunan ng modelo

| Natutunan                        | Halaga        | Ibig sabihin |
| -------------------------------- | ------------- | ------------ |
| Karaniwang galaw (**μ**)         | **+₱1.1111**  | Sa karaniwan, tumataas nang mga ₱1.11 bawat araw. |
| Ugali ng pagbabago (**φ**)       | **−0.8832**   | Malakas na **pabalik-balik**: kapag tumaas nang malaki kahapon, malamang bababa ngayon, at vice versa. |
| Epekto ng sorpresa (**θ**)       | **−0.1366**   | Maliit lang ang epekto ng sorpresa. |
| Karaniwang mintis (**σ**)        | **₱0.7659**   | Sa karaniwan, ganito kalayo ang hula sa totoo. |

### Hakbang 7: Paghula

**Araw 11 (bukas).** Bumaba nang ₱1 kahapon (Araw 9 → 10). Dahil
"pabalik-balik" ang ugali, inaasahan ng modelo na **babawi pataas** ngayon:

```
Pagbabagong inaasahan = 1.1111 + (−0.8832)×(−1 − 1.1111) + (−0.1366)×(0.4403)
                      = 1.1111 + 1.8645 − 0.0601
                      = +₱2.92

Hula sa Araw 11 = ₱110 + ₱2.92 = ₱112.92
```

**Araw 12.** Ang "kahapon" ngayon ay ang **hula** sa Araw 11 (+₱2.92). Dahil
tumaas nang malaki, inaasahang **bababa nang kaunti**:

```
Pagbabagong inaasahan = 1.1111 + (−0.8832)×(2.9154 − 1.1111) = −₱0.48
Hula sa Araw 12 = ₱112.92 − ₱0.48 = ₱112.43
```

**Araw 13.** Bumaba sa hula, kaya **babawi pataas** ulit:

```
Pagbabagong inaasahan = 1.1111 + (−0.8832)×(−0.4824 − 1.1111) = +₱2.52
Hula sa Araw 13 = ₱112.43 + ₱2.52 = ₱114.95
```

> Sa Araw 12 at 13, **wala nang "sorpresa"** sa pormula. Hindi pa natin alam
> ang mga sorpresa sa hinaharap, kaya ipinapalagay na zero ang mga ito.

### Hakbang 8: Ang resulta

| Araw              | Hula        | Saklaw (95%)         |
| ----------------- | ----------: | -------------------- |
| 11 (bukas)        | **₱112.92** | ₱111.41 – ₱114.42    |
| 12 (makalawa)     | **₱112.43** | ₱110.31 – ₱114.56    |
| 13                | **₱114.95** | ₱112.35 – ₱117.55    |

**Trend: PATAAS.** Ang average ng huling 7 totoong araw (Araw 4 hanggang 10)
ay **₱106.71**. Ang hula sa Araw 13 (₱114.95) ay **7.7% na mas mataas** doon,
lampas sa ±2% na hangganan.

> ✔ Ang mga numerong ito ay sinusuri ng isang awtomatikong test sa system
> (`tests/Unit/ArimaServiceTest.php`), kaya siguradong tugma ang dokumentong
> ito sa aktuwal na kuwenta.

---

## 7. Halimbawa 2: Forecast ng Suplay

Kabuuang kilo ng isang isda na dumating bawat araw:

| Araw     |  1 |  2 |  3 |  4 |  5 |  6 |  7 |  8 |  9 | 10 |
| -------- | -: | -: | -: | -: | -: | -: | -: | -: | -: | -: |
| Suplay   | 40 | 42 | 39 | 44 | 41 | 45 | 43 | 47 | 44 | 48 |

Pagbabago bawat araw: **+2, −3, +5, −3, +4, −2, +4, −3, +4**

| Natutunan                   | Halaga         | Ibig sabihin |
| --------------------------- | -------------- | ------------ |
| Karaniwang galaw (**μ**)    | **+0.8889 kg** | Unti-unting dumarami, mga 0.9 kg bawat araw. |
| Ugali ng pagbabago (**φ**)  | **−0.8977**    | Malakas na pabalik-balik: maraming dating, tapos kaunti, tapos marami ulit. |
| Epekto ng sorpresa (**θ**)  | **−0.5868**    | Mas malaki ang epekto ng sorpresa kaysa sa Halimbawa 1. |
| Karaniwang mintis (**σ**)   | **1.1683 kg**  |  |

Paghula:

```
Araw 11:  −1.68 kg  →  48.00 − 1.68 = 46.32 kg   (marami kahapon, kaya kaunti bukas)
Araw 12:  +3.20 kg  →  46.32 + 3.20 = 49.51 kg
Araw 13:  −1.18 kg  →  49.51 − 1.18 = 48.33 kg
```

| Araw   | Hula         | Saklaw (95%)        |
| ------ | -----------: | ------------------- |
| 11     | **46.32 kg** | 44.03 – 48.61 kg    |
| 12     | **49.51 kg** | 46.28 – 52.75 kg    |
| 13     | **48.33 kg** | 44.37 – 52.30 kg    |

**Trend: PATAAS.** Ang average ng huling 7 araw ay **44.57 kg**, at ang hula
sa Araw 13 (48.33 kg) ay **8.4% na mas mataas**.

> **Ano ang praktikal na gamit nito?** Kung inaasahang dadami ang suplay ng
> isang isda, puwedeng asahan ng supervisor na **bababa ang presyo** nito, o
> maghanda ng mas maraming espasyo sa palengke.

---

## 8. Ang "saklaw" o range: bakit hindi iisang numero lang

Walang makakahula nang eksakto sa presyo bukas. Kaya bukod sa hula, nagbibigay
ang sistema ng **saklaw**: ang pinakamababa at pinakamataas na makatwirang
mangyari.

- Ang **95% na saklaw** ay nangangahulugang: *"Sa 100 beses na ganito ang
  sitwasyon, mga 95 beses na papasok ang totoong presyo sa loob ng saklaw na
  ito."*
- **Lumalapad ang saklaw habang lumalayo ang araw.** Mas sigurado tayo sa bukas
  kaysa sa makalawa. Sa Halimbawa 1:

| Araw | Lapad ng saklaw | Bakit                                |
| ---- | --------------: | ------------------------------------ |
| 1    |           ₱3.01 | Pinakamalapit, pinakasigurado         |
| 2    |           ₱4.25 | Mga 1.4 beses na mas malapad          |
| 3    |           ₱5.20 | Mga 1.7 beses na mas malapad          |

> **Malapad na saklaw = hindi sira ang sistema.** Ibig sabihin lang nito,
> **magulo ang galaw** ng isdang iyon sa nakaraan, at **tapat** ang modelo na
> sabihing hindi ito sigurado. Mas mabuti iyon kaysa magkunwaring eksakto.

---

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
| **3-Day Trend**    | Pataas, Pababa, o Stable (Seksyon 9). |
| **Avg Forecast**   | Average ng tatlong hula. |
| **Lowest / Highest** | Pinakamababa at pinakamataas na hula sa 3 araw. |

### Ang graph

| Makikita sa graph       | Ibig sabihin |
| ----------------------- | ------------ |
| **Historical** (linya)  | Ang **totoong** presyo o suplay sa nakaraang 30 araw. |
| **Forecast** (linya)    | Ang **hula** para sa susunod na 3 araw. |
| **Mapusyaw na asul na bahagi** | Ang **saklaw** (Lower CI hanggang Upper CI). |
| **"Today"**             | Ang hangganan sa pagitan ng nakaraan at ng hula. |

### Ang talaan sa ibaba (Forecast Breakdown)

| Kolum          | Ibig sabihin |
| -------------- | ------------ |
| **Day / Date** | Aling araw ang hinuhulaan. |
| **Predicted**  | Ang hula. |
| **Lower CI / Upper CI** | Pinakamababa at pinakamataas sa saklaw. |
| **Δ Change**   | Pagbabago mula sa naunang araw. |
| **Trend**      | Ang trend ng buong 3 araw. |

---

## 11. Kailan walang forecast

Kailangan ng ARIMA ng **hindi bababa sa 7 araw** na may kumpirmadong batch ng
isang isda (sa loob ng huling 90 araw). Kung kulang:

- Hindi gagawa ng hula ang sistema. **Sinasadya ito.** Mas mabuting walang hula
  kaysa sa hulang galing sa hula-hula.
- Sa listahan ng isda, may nakasulat na **"· not enough data"**.
- Sa graph, may mensaheng nagsasabing kailangan pa ng kasaysayan.

Normal na **maraming isda ang walang forecast**. May 125 na uri ng isda sa
system, pero iilan lang ang regular na ibinebenta. Kapag nagsimula nang
magbenta ang mga vendor ng isang isda araw-araw, lalabas ang forecast nito
pagkalipas ng isang linggo.

---

## 12. Kailan ina-update ang forecast

| Kailan                         | Ano ang nangyayari |
| ------------------------------ | ------------------ |
| **Araw-araw, 12:01 ng hatinggabi** | Awtomatikong gumagawa ng bagong forecast ang system para sa lahat ng isdang may sapat na datos (kailangang tumatakbo ang *scheduler* sa server). |
| **Kapag binuksan ang pahina at walang nakaimbak na forecast** | Gumagawa agad ang pahina ng forecast para sa napiling isda. Kaya kahit hindi tumakbo ang scheduler, hindi mananatiling blangko ang pahina. |
| **Mano-mano**                  | Puwedeng patakbuhin ng developer ang `php artisan forecast:generate`. |

> Dahil hindi kasama ang araw ngayon (Seksyon 2), **hindi magbabago ang
> forecast sa buong araw** kahit may bagong batch na makumpirma. Papasok ang
> mga iyon sa forecast bukas.

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

Para makita ang forecast nang maayos, may espesyal na seeder: ang
**`ForecastDemoSeeder`**. Gumagawa ito ng **90 araw ng makatotohanang datos**
para sa **6 na isda**, bawat isa may sariling "kuwento":

| Isda                        | Kuwento                                              | Inaasahang trend sa presyo |
| --------------------------- | ---------------------------------------------------- | -------------------------- |
| **Hipon**                   | Tag-kaunti ang huli: sa huling 3 linggo, umuunti ang huli at tumataas ang presyo | Pataas |
| **Pusit**                   | Sobra ang huli: sa huling 3 linggo, dumarami ang dating at bumababa ang presyo | Pababa |
| **Tangigue (natural)**      | Dahan-dahang tumataas ang presyo                     | Stable o bahagyang pataas |
| **Bangus (medium)**         | Steady, pero mas mahal tuwing weekend               | Stable (puwedeng "Pataas" kung weekend ang huling datos, tingnan ang Seksyon 14) |
| **Galunggong (saday payo)** | Steady, mas maraming dating tuwing weekend          | Stable |
| **Tilapia**                 | Farm-raised, halos hindi nagbabago                  | Stable |

Ang bawat araw ay may 3 hanggang 4 na vendor. Paminsan-minsan, nagdadala ang
isang vendor ng **dalawang batch** sa isang araw. Lahat ng lumang batch ay
**sold out** na, kaya hindi sila lalabas bilang stale stock sa mga vendor.

**Paano patakbuhin** (gagawin ng developer):

```bash
php artisan db:seed --class=ForecastDemoSeeder
```

- Ang **lumang datos lang ng 6 na isdang ito** ang pinapalitan nito. Hindi
  ginagalaw ang ibang isda o ang mga batch ngayong araw.
- Awtomatiko rin nitong ginagawa ang forecast ng 6 na isda.
- Kasama na rin ito sa `php artisan migrate:fresh --seed`.

Pagkatapos, buksan ang **Forecasts**. Ang unang lalabas ay ang isdang may
**pinakamaraming datos**.

> Dahil ang demo data ay laging nakabatay sa **araw ngayon**, bahagyang
> nagbabago ang trend depende kung anong araw ito pinatakbo (lalo na kung
> weekend ang mga huling araw).

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

Tanging mga araw na **may datos** ang kasama, mula `ngayon − 90` hanggang
`kahapon`. Kailangan ng `n ≥ 7` na araw.

### B. Unang pagkakaiba (d = 1)

```
Δy[t] = y[t] − y[t−1]
```

### C. Karaniwang pagbabago (μ)

```
μ = (1 / m) · Σ Δy[t]            kung saan m = bilang ng pagkakaiba
```

### D. Coefficient ng AR(1): φ

Lag-1 autocorrelation ng pagkakaiba, nilimitahan sa [−0.99, 0.99]:

```
        Σ (Δy[t] − μ)(Δy[t+1] − μ)
φ  =  ─────────────────────────────
           Σ (Δy[t] − μ)²
```

*Halimbawa 1:* `φ = −34.3457 ÷ 38.8889 = −0.8832`

### E. Residual (ε) at coefficient ng MA(1): θ

Ang residual ay ang bahagi ng pagbabago na hindi naipaliwanag ng AR:

```
ε[t] = (Δy[t] − μ) − φ · (Δy[t−1] − μ)          (sa unang araw, Δy[t−1] = μ)
```

Ang θ ay ang lag-1 autocorrelation ng mga residual, nilimitahan din sa [−0.99, 0.99]:

```
        Σ (ε[t] − ε̄)(ε[t+1] − ε̄)
θ  =  ───────────────────────────
           Σ (ε[t] − ε̄)²
```

*Halimbawa 1:* mga residual = −0.1111, 0.7908, −1.3261, 0.0244, −0.4429,
1.0244, 0.4403, 1.0244, 0.4403 → `θ = −0.6412 ÷ 4.6931 = −0.1366`

> **Tala sa pagtantiya:** Ito ay pinasimpleng *method of moments* (estilong
> Hannan–Rissanen). Ang θ ay direktang kinukuha mula sa autocorrelation ng
> residual, sa halip na lutasin mula sa `ρ₁ = θ / (1 + θ²)` o tantiyahin sa
> maximum likelihood. Sapat ito para sa 3-araw na hula at madaling i-check sa
> kamay, pero hindi ito kapareho ng `statsmodels` o ng `auto.arima` ng R.

### F. Karaniwang mintis (σ)

Sample standard deviation ng mga residual:

```
σ = √[ Σ (ε[t] − ε̄)² / (m − 1) ]
```

*Halimbawa 1:* `σ = 0.7659`

### G. Paghula (h = 1, 2, 3)

```
Δŷ[T+1] = μ + φ · (Δy[T] − μ)     + θ · ε[T]       ← Araw 1: huling totoong pagbabago at residual
Δŷ[T+h] = μ + φ · (Δŷ[T+h−1] − μ) + θ · 0          ← Araw 2 at 3: zero ang sorpresa sa hinaharap

ŷ[T+h]  = max(0, ŷ[T+h−1] + Δŷ[T+h])               ← hindi puwedeng negatibo
```

### H. Saklaw (95% prediction interval)

```
band(h)  = z · σ · √h              z = 1.96
min      = max(0, ŷ − band)
max      = ŷ + band
```

*Halimbawa 1:* band = 1.50, 2.12, 2.60 para sa h = 1, 2, 3.

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
| `history_days`        | `FORECAST_HISTORY_DAYS`       | `90`    | Ilang araw ang ginagamit sa pag-aaral ng modelo |
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
| `app/Console/Commands/GenerateForecasts.php`       | `php artisan forecast:generate` |
| `app/Http/Controllers/Supervisor/ForecastController.php` | Ang pahina ng Forecasts |
| `database/seeders/ForecastSeeder.php`              | Gumagawa ng forecast para sa lahat ng isda |
| `database/seeders/ForecastDemoSeeder.php`          | Makatotohanang demo data para sa 6 na isda |
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
| **μ (mu)**              | Karaniwang pagbabago bawat araw. |
| **σ (sigma)**           | Karaniwang laki ng mintis ng modelo. |
| **Residual**            | Ang bahaging hindi naipaliwanag ng modelo, ang "sorpresa." |
| **Saklaw / CI**         | *Confidence interval*. Ang pinakamababa at pinakamataas na makatwirang mangyari. |
| **95%**                 | Mga 95 sa bawat 100 beses, papasok ang totoong halaga sa saklaw. |
| **Trend**               | Pataas, Pababa, o Stable, kumpara sa huling 7 araw. |
| **Backtest**            | Pagsubok sa modelo gamit ang lumang datos na alam na ang sagot. |
| **Seeder**              | Programang naglalagay ng sample na datos sa database. |
