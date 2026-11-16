<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\ContactMethod;
use App\Language;
use App\Skill;

class InitialCoreDataSeeder extends Seeder
{
    /**
     * Seed core data required for the application to operate.
     */
    public function run(): void
    {
        $this->seedRoles();
        $this->seedContactMethods();
        $this->seedLanguages();
        $this->seedSkills();
    }

    private function seedRoles(): void
    {
        $roles = [
            [ 'id' => 1, 'name' => 'super-admin' ],
            [ 'id' => 2, 'name' => 'admin' ],
            [ 'id' => 3, 'name' => 'client' ],
            [ 'id' => 4, 'name' => 'new-client' ],
            [ 'id' => 5, 'name' => 'agent' ],
            [ 'id' => 6, 'name' => 'translator' ],
            [ 'id' => 7, 'name' => 'qualified-interpreter' ],
            [ 'id' => 8, 'name' => 'community-interpreter' ],
            [ 'id' => 9, 'name' => 'new-agent' ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['id' => $role['id']], $role);
        }
    }

    private function seedContactMethods(): void
    {
        $contactMethods = [
            ['id' => 1, 'contact_method' => 'email'],
            ['id' => 2, 'contact_method' => 'sms'],
            ['id' => 3, 'contact_method' => 'phone'],
        ];

        foreach ($contactMethods as $contactMethod) {
            ContactMethod::updateOrCreate(['id' => $contactMethod['id']], $contactMethod);
        }
    }

    private function seedLanguages(): void
    {
        $languages = [
            'Afrikaans','Arabic','Adioukrou','Albanian','Akan','Amazigh','Amharic','Armenian','Ashanti','Azari','Azerbaijani','Badini','Belarussian','Bengali','Berber','Bosnian','Burushaski','BSL','Bulgarian','Burmese','Burgundian','Burundi','Bajuni','Baluchi','Cantonese','Catlan','Chinese','Croatian','Czech','Capeverdean Kriol','Danish','Dari','Diola','Dioala','Dutch','Estonian','English','Farsi','Finnish','Flemish','French','Fula','Gag','Gaelic(Welsh)','Gaelic(Scottish)','Georgia','German','Gorani','Greek','Gujarati','Hakka','Hebrew','Hindi','Hindko','Hungarian','Hausa','Icelandic','Igbo','Indonesian','Italian','Illongo','Ishan','Japanese','Kathiawadi','Kazakh','Kosovan','Korean','Karatianian','Kurdish','Kurmanji','Kannada','Kuchi','Kitubu','Kirundi','Kurmanji','Kimyarwandi','Kikuyu','Kiswahali','Khuwar','Latvian','Lingala','Lithuanian','Macedonian','Malayalam','Malinke','Mandarin','Marathi','Mirpuri','Mongolian','Malay','Mandinka','Montenergrin','Nepali','Norwegian','Ndebele','Panjabi','Pashto','Pidgin English','Polish','Portuguese','Potawari','Romanian','Russian','Rwanda','Serbian','Sinhalese','Slovakian','Slovenia','Somali','Susu','Spanish','Swahili','Swedish','Sylhetti','Sorani','Shona','Serbo-Croatian','Shinia','Tagalog','Tamil','Telugu','Tigranian','Tshiluba','Turkish','Twi','Taiwanese','Tigre','Thai','Ukrainian','Urdu','Uzbek','Vietnamese','Venda','Welsh','Wakhi','Woolof','Xhosa','Yemeni','Yoruba','Zulu'
        ];

        foreach ($languages as $name) {
            Language::firstOrCreate(['name' => $name]);
        }
    }

    private function seedSkills(): void
    {
        $skills = [
            ['id' => 1, 'skill' => 'Face to Face'],
            ['id' => 2, 'skill' => 'BSL'],
            ['id' => 3, 'skill' => 'Telephone Interpreting'],
            ['id' => 4, 'skill' => 'Video Interpreting'],
            ['id' => 5, 'skill' => 'Simultaneous'],
            ['id' => 6, 'skill' => 'Consecutive'],
            ['id' => 7, 'skill' => 'Translation', 'type' => 1],
            ['id' => 8, 'skill' => 'Transcription', 'type' => 1],
            ['id' => 9, 'skill' => 'Text to Speech', 'type' => 1],
        ];

        foreach ($skills as $skill) {
            Skill::updateOrCreate(['id' => $skill['id']], $skill);
        }
    }
}