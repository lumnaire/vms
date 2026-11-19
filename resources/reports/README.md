# Official fish entrance forms

These assets are extracted, without alteration, from the researchers' supplied
Word documents. Keep the report wording, artwork and column order consistent
with those references.

- `daily-seal.png`: seal from `Fish-Entrance-Form-Daily_Revised.docx` (unchanged
  from the original daily form).
- `yearly-seal.png`: seal from `fish-entrance-form-2026-yearly.docx`.
- `monthly-form.png`: original certificate artwork from `monthlyreport.docx`,
  including the border and Josephine A. Gianan / Administrative Aide I signatory.
  The PDF fills the name, kilograms and selected period over this artwork.

`FishEntrancePdf` draws legal-size pages directly using Dompdf's existing CPDF
canvas. Daily forms follow `Fish-Entrance-Form-Daily_Revised.docx`: six columns
(Date, Name of Supplier/Vendor, Kilo’s, Price, Fish Class, Fish Type), the revised
table widths and indent, and fifty writing rows.
Monthly forms have four certificates per landscape page. Yearly forms have one
vendor per landscape page, thirty-one date rows, twelve paired Kg/Price columns,
and a totals row. Empty cells, including nonexistent dates, remain blank.

Only confirmed initial batch quantities count as fish entrance, including old
confirmed stock that has since sold out, expired or been pulled out. Total Price
is `SUM(stock_kg * price_per_kg)`, not sales revenue. The revised daily form has
no arrival-time field.

The annual query returns at most 366 daily aggregates per vendor. Daily rows and
vendor lists are read in chunks; no full year of Eloquent models or HTML table
layout trees is loaded for PDF export. Preview requests do not create archives
or activity logs. Successful downloads archive the period, vendor and totals.

On Windows the installed Calibri and Bodoni MT Poster Compressed fonts are used
and embedded. For an equivalent deployment elsewhere, set
`REPORT_FONT_DIRECTORY` to a directory containing licensed `calibri.ttf`,
`calibrib.ttf` and `BOD_PSTC.TTF` files. Built-in PDF fonts are the fallback when
these are unavailable; the form geometry and artwork stay the same.
