<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FishType;

class FishTypeSeeder extends Seeder
{
    public function run(): void
    {
        $fishTypesByClass = [
            'First Class' => [
                'Bammer (yellow pin)',
                'Bangkulis',
                'Bighu',
                'Bolangawan',
                'Calapion/Talakitok (malagimago)',
                'Lana',
                'Lapu-Lapu (baraca)',
                'Malagono',
                'Malasuge',
                'Maya-Maya',
                'Tangigue (batang)',
                'Tangigue (natural)',
            ],
            'Second Class' => [
                'Aguas',
                'Amongtol',
                'Angol',
                'Atoloy (local)',
                'Atoloy (mainland)',
                'Balanak (large)',
                'Bangkulis (white fin)',
                'Bangus (large)',
                'Bangus (medium)',
                'Borao',
                'Bukawon',
                'Bulgan',
                'Canduli',
                'Cano-os',
                'Cataway (large)',
                'Cataway (medium)',
                'Colambotan',
                'Cugita',
                'Dalagang Bukid',
                'Danoy',
                'Dugso',
                'Durado (big)',
                'Guribas (large)',
                'Kamasohon',
                'Labong',
                'Malugui-mango',
                'Manabang',
                'Manitis',
                'Murinay',
                'Pusit',
                'Sandig',
                'Sapi',
                'Sapsap (large)',
            ],
            'Third Class' => [
                'Agoot',
                'Aguigayon',
                'Bangus (small)',
                'Bisugo',
                'Bolinaw (itom)',
                'Bolinaw (puti)',
                'Bugiw',
                'Buskayan',
                'Cabsi',
                'Cabonbon',
                'Canasi (bisugo)',
                'Carlos',
                'Cataway (small)',
                'Durado (small)',
                'Duwal-bilog',
                'Duwal-lapad',
                'Galunggong (dakula payo)',
                'Galunggong (saday payo)',
                'Guribas (small/medium)',
                'Haluan',
                'Hito',
                'Kuyog',
                'Kuwaw',
                'Lambungayaw',
                'Lampanigan/Mangaldit',
                'Langkoy',
                'Lupani',
                'Molbong',
                'Maming',
                'Manamsi',
                'Mangaldit',
                'Pak-an',
                'Pagui',
                'Pating (arado)',
                'Pating (balanakon)',
                'Salay-salay',
                'Sapsap (small/medium)',
                'Sibubog',
                'Surahan (brown)',
                'Surahan (itom)',
                'Sapan',
                'Talongan/Olapay',
                'Tangirion',
                'Tatos',
                'Tibus/Mangaldit',
                'Tilapia',
                'Tolong',
                'Toros',
                'Turay',
                'Talagbago',
                'Turingan',
            ],
            'Fourth Class' => [
                'Guno',
                'Ilid-Ilid',
                'Karibangbang',
                'Kikilo',
                'Lapis',
                'Pagan/Bagtak',
                'Palad',
                'Pating (langit-langit)',
                'Patona',
                'Pugot',
                'Pututan',
                'Putyukan',
                'Riliw',
                'Suga',
                'Tahong',
                'Tamban',
                'Tiki',
                'Turay-Turay',
                'Ubod',
            ],
            'Special Class' => [
                'Banagan (lobster) headless',
                'Bungkang/Aringawon',
                'Buyod (uncultured)',
                'Casili',
                'Copapha',
                'Hipon',
                'Kabkab',
                'Kano-os/Squid',
                'Moting-Tabagwang',
                'Sugpo',
            ],
        ];

        // Deactivate fish types no longer in the official list so they are hidden
        // from dropdowns, while keeping historical records referencing them intact.
        FishType::query()->update(['is_active' => false]);

        foreach ($fishTypesByClass as $qualityClass => $names) {
            foreach ($names as $name) {
                FishType::updateOrCreate(
                    ['name' => $name],
                    [
                        'quality_class' => $qualityClass,
                        'is_active'     => true,
                    ]
                );
            }
        }
    }
}