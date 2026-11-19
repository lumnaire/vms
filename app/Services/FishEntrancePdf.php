<?php

namespace App\Services;

use Carbon\Carbon;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;

/**
 * Draw the supplied Word forms directly onto PDF pages. No HTML table tree is
 * retained by Dompdf, even when a report contains many vendors or daily entries.
 * Dimensions are points, taken from the references' legal-size page geometry.
 */
class FishEntrancePdf
{
    private CPDF $canvas;

    private string $font = 'Times-Roman';

    private string $boldFont = 'Times-Bold';

    private string $tableHeadingFont = 'Times-Bold';

    public function render(FishEntranceReport $report): string
    {
        $dompdf = $this->fonts($report->range->period);
        $this->canvas = new CPDF('legal', $report->range->period === 'daily' ? 'portrait' : 'landscape', $dompdf);
        $this->canvas->add_info('Title', ucfirst($report->range->period).' Records of Fish Entrance — '.$report->range->label());

        match ($report->range->period) {
            'monthly' => $this->monthly($report),
            'yearly' => $this->yearly($report),
            default => $this->daily($report),
        };

        return $this->canvas->output();
    }

    private function fonts(string $period): Dompdf
    {
        $dompdf = new Dompdf;
        $this->font = $dompdf->getFontMetrics()->getFont('times', 'normal');
        $this->boldFont = $this->tableHeadingFont = $dompdf->getFontMetrics()->getFont('times', 'bold');
        $directory = config('reports.font_directory');
        if ($period === 'monthly' || ! $directory || ! is_file($directory.'/calibri.ttf')) {
            return $dompdf;
        }

        $cache = storage_path('app/private/report-fonts');
        if (! is_dir($cache)) {
            mkdir($cache, 0755, true);
        }
        // Configure the font cache before FontMetrics loads its installed families.
        $dompdf = new Dompdf(['fontDir' => $cache, 'fontCache' => $cache, 'chroot' => [$directory, $cache]]);
        $metrics = $dompdf->getFontMetrics();
        foreach ([['calibri.ttf', 'Calibri', 'normal'], ['calibrib.ttf', 'Calibri', 'bold'],
            ['BOD_PSTC.TTF', 'Bodoni MT Poster Compressed', 'bold']] as [$file, $family, $weight]) {
            if (is_file($directory.'/'.$file)) {
                $metrics->registerFont(['family' => $family, 'style' => 'normal', 'weight' => $weight], $directory.'/'.$file);
            }
        }
        $this->font = $metrics->getFont('Calibri', 'normal') ?? $this->font;
        $this->boldFont = $metrics->getFont('Calibri', 'bold') ?? $this->boldFont;
        $this->tableHeadingFont = $period === 'yearly'
            ? ($metrics->getFont('Bodoni MT Poster Compressed', 'bold') ?? $this->boldFont)
            : $this->boldFont;

        return $dompdf;
    }

    private function daily(FishEntranceReport $report): void
    {
        $page = 0;
        // The reference has one heading row and fifty writing rows.
        foreach ($report->dailyRows()->chunk(50) as $rows) {
            if ($page++ > 0) {
                $this->canvas->new_page();
            }
            $this->dailyPage($report, $rows->all());
        }
        if ($page === 0) {
            $this->dailyPage($report, []);
        }
    }

    private function dailyPage(FishEntranceReport $report, array $rows): void
    {
        $this->canvas->image(resource_path('reports/daily-seal.png'), 453, 72, 72, 69);
        $this->center('Office of the Mayor', 72, 72, 468, 16, 12);
        $this->center('Market and Slaughterhouse Operations', 72, 87, 468, 16, 12);
        $this->center('VIRAC PUBLIC MARKET', 72, 102, 468, 16, 12, true);
        $this->center('DAILY RECORDS OF FISH ENTRANCE', 72, 132, 468, 16, 12, true);
        $this->center('For the Month of '.$report->range->start->format('F Y'), 72, 147, 468, 16, 12);

        // Column widths and indent from Fish-Entrance-Form-Daily_Revised.docx.
        $widths = array_map(fn ($w) => $w / 20, [1313, 2430, 1170, 1260, 1530, 1710]);
        $headers = ['DATE', 'Name of Supplier/ Vendor', 'Kilo’s', 'Price', 'Fish Class', 'Fish Type'];
        $top = 182.0;
        $left = 71.75; // Word's 1 inch margin plus the revised table's -5 DXA indent.
        $right = $left + array_sum($widths);
        $bottom = $top + 28 + 50 * 14.4;
        // Draw each grid line once so blank cells retain their column borders.
        $this->canvas->line($left, $top, $right, $top, [0, 0, 0], 0.5);
        for ($i = 0; $i <= 50; $i++) {
            $y = $top + 28 + $i * 14.4;
            $this->canvas->line($left, $y, $right, $y, [0, 0, 0], 0.5);
        }
        $x = $left;
        foreach ($headers as $index => $header) {
            $this->canvas->line($x, $top, $x, $bottom, [0, 0, 0], 0.5);
            $this->center($header, $x + 2, $top, $widths[$index] - 4, 28, 11, true, $this->tableHeadingFont);
            $x += $widths[$index];
        }
        $this->canvas->line($right, $top, $right, $bottom, [0, 0, 0], 0.5);
        $rows = array_values($rows);
        for ($i = 0; $i < 50; $i++) {
            $row = $rows[$i] ?? null;
            $values = $row ? [
                Carbon::parse($row->entry_date)->format('m/d/Y'),
                $row->vendor, number_format($row->stock_kg, 2),
                number_format($row->price_per_kg, 2), $row->quality_class, $row->fish ?? '',
            ] : array_fill(0, 6, '');
            $x = $left;
            foreach ($values as $column => $value) {
                $this->center($value, $x + 2, $top + 28 + $i * 14.4, $widths[$column] - 4, 14.4, 9);
                $x += $widths[$column];
            }
        }
    }

    private function monthly(FishEntranceReport $report): void
    {
        $page = 0;
        foreach ($report->vendors()->chunk(4) as $vendors) {
            if ($page++ > 0) {
                $this->canvas->new_page();
            }
            $this->monthlyPage($report, $vendors->values()->all());
        }
        if ($page === 0) {
            $this->monthlyPage($report, []);
        }
    }

    private function monthlyPage(FishEntranceReport $report, array $vendors): void
    {
        // Four certificates on landscape legal paper, as in monthlyreport.docx.
        foreach ([[73.2, 36], [523, 36], [73.2, 326], [523, 326]] as $index => [$x, $y]) {
            $width = 410.6;
            $height = 241.7;
            $this->canvas->image(resource_path('reports/monthly-form.png'), $x, $y, $width, $height);
            // Preserve the original artwork, border, seal and signatory; replace only the period.
            $this->canvas->filled_rectangle($x + 0.12 * $width, $y + 0.47 * $height, 0.76 * $width, 0.065 * $height, [1, 1, 1]);
            $this->center('FOR THE PERIOD OF '.strtoupper($report->range->start->format('F Y')),
                $x + 0.12 * $width, $y + 0.47 * $height, 0.76 * $width, 0.065 * $height, 13);
            if (isset($vendors[$index])) {
                $vendor = $vendors[$index];
                $this->center($vendor->name, $x + 0.33 * $width, $y + 0.253 * $height, 0.63 * $width, 0.042 * $height, 12);
                $this->center(number_format($vendor->kg, 2), $x + 0.64 * $width, $y + 0.384 * $height, 0.32 * $width, 0.042 * $height, 12);
            }
        }
    }

    private function yearly(FishEntranceReport $report): void
    {
        $page = 0;
        foreach ($report->vendors() as $vendor) {
            if ($page++ > 0) {
                $this->canvas->new_page();
            }
            $this->yearlyPage($report, $vendor->name, $report->yearlyGrid($vendor->id));
        }
        if ($page === 0) {
            $this->yearlyPage($report, '', ['days' => [], 'months' => []]);
        }
    }

    private function yearlyPage(FishEntranceReport $report, string $vendor, array $grid): void
    {
        $this->canvas->image(resource_path('reports/yearly-seal.png'), 23.4, 9, 70.7, 67.5);
        $this->center('Market and Slaughterhouse Operations', 14.4, 36, 936, 16, 12, true);
        // The yearly reference deliberately uses this same title, including its year.
        $this->center('DAILY RECORDS OF FISH ENTRANCE '.$report->range->start->format('Y'), 14.4, 51, 936, 16, 12, true);
        $this->canvas->text(560, 76, 'Name of Vendor:', $this->boldFont, 11);
        $this->canvas->line(645, 89, 950.4, 89, [0, 0, 0], 0.5);
        $this->center($vendor, 647, 74, 301, 15, 11, true);

        $widths = array_map(fn ($w) => $w / 20,
            [757, 683, 720, 720, 741, 699, 720, 720, 720, 720, 720, 720, 720,
                720, 720, 720, 720, 720, 720, 720, 900, 720, 720, 720, 720]);
        $top = 98.0;
        $x = 14.4;
        $this->cell('', $x, $top, $widths[0], 42, 12, true);
        $this->canvas->save();
        $this->canvas->rotate(-90, $x + $widths[0] / 2, $top + 21);
        $this->center('Date', $x + $widths[0] / 2 - 21, $top + 21 - $widths[0] / 2, 42, $widths[0], 12, true, $this->tableHeadingFont);
        $this->canvas->restore();
        $x += $widths[0];
        for ($month = 1; $month <= 12; $month++) {
            $kgWidth = $widths[$month * 2 - 1];
            $priceWidth = $widths[$month * 2];
            $this->cell(Carbon::create(2000, $month, 1)->format('F'), $x, $top, $kgWidth + $priceWidth, 17, 12, true);
            $this->cell('Total Kg', $x, $top + 17, $kgWidth, 25, 12, true);
            $this->cell('Total Price', $x + $kgWidth, $top + 17, $priceWidth, 25, 12, true);
            $x += $kgWidth + $priceWidth;
        }
        for ($day = 1; $day <= 31; $day++) {
            $y = $top + 42 + ($day - 1) * 13.4;
            $x = 14.4;
            $this->cell($day.'.', $x, $y, $widths[0], 13.4, 10);
            $x += $widths[0];
            for ($month = 1; $month <= 12; $month++) {
                foreach (['kg', 'price'] as $offset => $key) {
                    $width = $widths[$month * 2 - 1 + $offset];
                    $value = $grid['days'][$day][$month][$key] ?? null;
                    $this->cell($value === null ? '' : number_format($value, 2), $x, $y, $width, 13.4, 8);
                    $x += $width;
                }
            }
        }
        $x = 14.4;
        $y = $top + 42 + 31 * 13.4;
        $this->cell('Total', $x, $y, $widths[0], 31, 10, true);
        $x += $widths[0];
        for ($month = 1; $month <= 12; $month++) {
            foreach (['kg', 'price'] as $offset => $key) {
                $width = $widths[$month * 2 - 1 + $offset];
                $this->cell(number_format($grid['months'][$month][$key] ?? 0, 2), $x, $y, $width, 31, 8, true);
                $x += $width;
            }
        }
    }

    private function cell(string $text, float $x, float $y, float $width, float $height, float $size, bool $bold = false): void
    {
        $this->canvas->rectangle($x, $y, $width, $height, [0, 0, 0], 0.5);
        $this->center($text, $x + 2, $y, $width - 4, $height, $size, $bold, $bold ? $this->tableHeadingFont : $this->font);
    }

    private function center(string $text, float $x, float $y, float $width, float $height, float $size, bool $bold = false, ?string $font = null): void
    {
        if ($text === '') {
            return;
        }
        $font ??= $bold ? $this->boldFont : $this->font;
        $text = preg_replace('/\s+/u', ' ', $text);
        $lines = [$text];
        // Keep headings legible by wrapping at word boundaries before reducing size.
        if ($height >= 22 && $this->canvas->get_text_width($text, $font, $size) > $width && str_contains($text, ' ')) {
            $words = explode(' ', $text);
            $middle = (int) ceil(count($words) / 2);
            $lines = [implode(' ', array_slice($words, 0, $middle)), implode(' ', array_slice($words, $middle))];
        }
        foreach ($lines as $line) {
            $textWidth = $this->canvas->get_text_width($line, $font, $size);
            if ($textWidth > $width) {
                $size *= $width / $textWidth;
            }
        }
        $lineHeight = $this->canvas->get_font_baseline($font, $size);
        if (count($lines) * $lineHeight > $height - 2) {
            $size *= ($height - 2) / (count($lines) * $lineHeight);
            $lineHeight = $this->canvas->get_font_baseline($font, $size);
        }
        $descender = $this->canvas->get_cpdf()->getFontDescender($size);
        foreach ($lines as $index => $line) {
            $textWidth = $this->canvas->get_text_width($line, $font, $size);
            $this->canvas->text($x + ($width - $textWidth) / 2,
                $y + ($height - count($lines) * $lineHeight) / 2 + $descender + $index * $lineHeight, $line, $font, $size);
        }
    }
}
