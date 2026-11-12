<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'ld-survey-2026';

    /**
     * Moves the Learning and Development Survey (until now written in code)
     * into the database, so admins can edit it in the question editor.
     *
     * The questions and Filipino translations come from TDI's "Survey Question
     * for Learning and Development" document. The answer keys and choice
     * values stay the same, so answers already sent still match. A title or
     * description an admin already changed is kept.
     */
    public function up(): void
    {
        $now = now();
        $existing = DB::table('public_forms')->where('slug', self::SLUG)->first();

        $texts = [
            'title' => 'Learning and Development Survey',
            'kicker' => 'CY 2026 · TESDA Development Institute',
            'description' => 'Tell us about your learning and development experience in CY 2026 and what you would like TDI to prioritize in 2027.',
        ];

        if ($existing === null) {
            $formId = DB::table('public_forms')->insertGetId(array_merge($texts, [
                'slug' => self::SLUG,
                'reference_prefix' => 'LDS',
                'is_open' => true,
                'hide_background' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        } else {
            $formId = $existing->id;

            DB::table('public_forms')->where('id', $formId)->update([
                'title' => $existing->title ?? $texts['title'],
                'kicker' => $existing->kicker ?? $texts['kicker'],
                'description' => $existing->description ?? $texts['description'],
                'reference_prefix' => 'LDS',
                'updated_at' => $now,
            ]);
        }

        if (DB::table('public_form_questions')->where('public_form_id', $formId)->exists()) {
            return;
        }

        foreach ($this->sections() as $sectionOrder => $section) {
            $sectionId = DB::table('public_form_sections')->insertGetId([
                'public_form_id' => $formId,
                'title' => $section['title'],
                'step_label' => $section['step_label'],
                'description' => $section['description'],
                'min_salary_grade' => $section['min_salary_grade'] ?? null,
                'sort_order' => $sectionOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($section['questions'] as $questionOrder => $question) {
                $options = isset($question['options'])
                    ? array_map(fn (array $option) => [
                        'value' => $option['value'],
                        'label' => $option['label'],
                        'label_fil' => $option['label_fil'] ?? null,
                        'score' => $option['score'] ?? null,
                        'exclusive' => $option['exclusive'] ?? false,
                    ], $question['options'])
                    : null;

                DB::table('public_form_questions')->insert([
                    'public_form_id' => $formId,
                    'public_form_section_id' => $sectionId,
                    'answer_key' => $question['key'],
                    'type' => $question['type'],
                    'question' => $question['question'],
                    'question_fil' => $question['question_fil'] ?? null,
                    'instruction' => $question['instruction'] ?? null,
                    'instruction_fil' => $question['instruction_fil'] ?? null,
                    'options' => $options === null ? null : json_encode($options, JSON_UNESCAPED_UNICODE),
                    'allow_other' => $question['allow_other'] ?? false,
                    'other_label' => ($question['allow_other'] ?? false) ? ($question['other_label'] ?? 'Other') : null,
                    'other_label_fil' => ($question['allow_other'] ?? false) ? ($question['other_label_fil'] ?? 'Iba pa') : null,
                    'is_required' => $question['required'] ?? true,
                    'rank_count' => $question['rank'] ?? null,
                    'max_choices' => $question['max'] ?? null,
                    'sort_order' => $questionOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Removes the survey's sections and questions (the form, its settings and
     * its responses stay).
     */
    public function down(): void
    {
        $formId = DB::table('public_forms')->where('slug', self::SLUG)->value('id');

        if ($formId !== null) {
            DB::table('public_form_questions')->where('public_form_id', $formId)->delete();
            DB::table('public_form_sections')->where('public_form_id', $formId)->delete();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sections(): array
    {
        $poorToExcellent = [
            ['value' => '1', 'score' => 1, 'label' => 'Very Poor', 'label_fil' => 'Napakahina'],
            ['value' => '2', 'score' => 2, 'label' => 'Poor', 'label_fil' => 'Mahina'],
            ['value' => '3', 'score' => 3, 'label' => 'Good', 'label_fil' => 'Maganda'],
            ['value' => '4', 'score' => 4, 'label' => 'Excellent', 'label_fil' => 'Napakahusay'],
        ];

        return [
            [
                'title' => 'Learning Priorities',
                'step_label' => 'Priorities',
                'description' => 'What you want to learn in 2027, how, and why.',
                'questions' => [
                    [
                        'key' => 'priority_areas',
                        'type' => 'ranking',
                        'rank' => 3,
                        'question' => 'Among the following learning and development areas, which would you like TDI to prioritize for your professional development in 2027?',
                        'question_fil' => 'Sa mga sumusunod na larangan ng pagkatuto at pagpapaunlad, alin ang nais ninyong bigyang-prayoridad ng TDI para sa inyong propesyonal na pag-unlad sa 2027?',
                        'instruction' => 'Rank your top three (3) preferred learning areas, with 1 as your highest priority.',
                        'instruction_fil' => 'Iranggo ang tatlong (3) larangang nais ninyong bigyang-prayoridad, kung saan ang 1 ang pinakamataas na prayoridad.',
                        'allow_other' => true,
                        'other_label' => 'Other (please specify)',
                        'other_label_fil' => 'Iba pa (pakitukoy)',
                        'options' => [
                            ['value' => 'ethics', 'label' => 'Ethics, integrity, and customer service', 'label_fil' => 'Etika, integridad, at mahusay na serbisyo sa publiko'],
                            ['value' => 'critical_thinking', 'label' => 'Critical thinking, problem-solving, and decision-making', 'label_fil' => 'Mapanuring pag-iisip, paglutas ng problema, at paggawa ng desisyon'],
                            ['value' => 'program_planning', 'label' => 'Program planning, project management, and evaluation', 'label_fil' => 'Pagpaplano ng programa, pamamahala ng proyekto, at ebalwasyon'],
                            ['value' => 'writing_presentation', 'label' => 'Writing and presentation skills', 'label_fil' => 'Kasanayan sa pagsulat at presentasyon'],
                            ['value' => 'leadership', 'label' => 'Leadership, change management, coaching, and mentoring', 'label_fil' => 'Pamumuno, pamamahala ng pagbabago, coaching, at mentoring'],
                            ['value' => 'digital_data_research', 'label' => 'Digital, data, and research skills', 'label_fil' => 'Kasanayan sa teknolohiya, pagsusuri ng datos, at pananaliksik'],
                            ['value' => 'wellness', 'label' => 'Wellness, mental health, and work values', 'label_fil' => 'Kagalingan, kalusugang pangkaisipan, at mga pagpapahalaga sa trabaho'],
                            ['value' => 'career_retirement', 'label' => 'Career planning and pre-retirement', 'label_fil' => 'Pagpaplano ng karera at paghahanda sa pagreretiro'],
                            ['value' => 'job_specific', 'label' => 'Job-specific skills (e.g., procurement, records, curriculum development)', 'label_fil' => 'Mga kasanayang kaugnay ng trabaho (hal., procurement, pamamahala ng rekord, at pagbuo ng kurikulum)'],
                        ],
                    ],
                    [
                        'key' => 'learning_approaches',
                        'type' => 'multiple',
                        'max' => 2,
                        'question' => 'Which learning approaches would you prefer for future L&D activities?',
                        'question_fil' => 'Aling mga paraan ng pagkatuto ang mas gusto ninyo para sa mga susunod na L&D activity?',
                        'instruction' => 'Select up to 2.',
                        'instruction_fil' => 'Pumili ng hanggang 2.',
                        'allow_other' => true,
                        'options' => [
                            ['value' => 'face_to_face', 'label' => 'Face-to-face training and workshops', 'label_fil' => 'Harapang pagsasanay at workshop'],
                            ['value' => 'live_online', 'label' => 'Live online training or webinars', 'label_fil' => 'Live online training o webinar'],
                            ['value' => 'self_paced', 'label' => 'Self-paced online courses', 'label_fil' => 'Online courses na maaaring tapusin sa sariling oras'],
                            ['value' => 'hands_on', 'label' => 'Hands-on activities and practical exercises', 'label_fil' => 'Praktikal na pagsasanay at aktuwal na gawain'],
                            ['value' => 'coaching_mentoring', 'label' => 'Coaching or mentoring', 'label_fil' => 'Coaching o mentoring'],
                            ['value' => 'peer_learning', 'label' => 'Peer learning and knowledge-sharing', 'label_fil' => 'Pagbabahagi ng kaalaman at pagkatuto mula sa mga kasamahan'],
                            ['value' => 'blended', 'label' => 'Blended learning (online and face-to-face)', 'label_fil' => 'Pinagsamang online at harapang pagsasanay'],
                        ],
                    ],
                    [
                        'key' => 'motivation',
                        'type' => 'single',
                        'question' => 'What primarily motivates you to participate in learning and development activities?',
                        'question_fil' => 'Ano ang pangunahing dahilan na naghihikayat sa inyong lumahok sa mga L&D activity?',
                        'allow_other' => true,
                        'options' => [
                            ['value' => 'job_performance', 'label' => 'Improving my performance in my current job', 'label_fil' => 'Pagpapahusay ng aking pagganap sa trabaho'],
                            ['value' => 'career_growth', 'label' => 'Career advancement and professional growth', 'label_fil' => 'Pag-angat sa karera at propesyonal na pag-unlad'],
                            ['value' => 'new_skills', 'label' => 'Acquiring new knowledge and skills', 'label_fil' => 'Pagkakaroon ng bagong kaalaman at kasanayan'],
                            ['value' => 'personal_interest', 'label' => 'Personal interest and fulfillment', 'label_fil' => 'Personal na interes at kasiyahan'],
                            ['value' => 'requirements', 'label' => 'Meeting organizational or job requirements', 'label_fil' => 'Pagtugon sa mga kinakailangan ng trabaho o organisasyon'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Your CY 2026 Experience',
                'step_label' => 'Experience',
                'description' => 'How the learning and development activities went for you this year.',
                'questions' => [
                    [
                        'key' => 'satisfaction',
                        'type' => 'single',
                        'question' => 'How satisfied are you with the learning and development opportunities made available by TESDA during CY 2026?',
                        'question_fil' => 'Gaano kayo nasiyahan sa mga oportunidad para sa pagkatuto at pagpapaunlad na inilaan ng TESDA noong 2026?',
                        'options' => [
                            ['value' => '1', 'score' => 1, 'label' => 'Very Dissatisfied', 'label_fil' => 'Lubos na Hindi Nasiyahan'],
                            ['value' => '2', 'score' => 2, 'label' => 'Dissatisfied', 'label_fil' => 'Hindi Nasiyahan'],
                            ['value' => '3', 'score' => 3, 'label' => 'Satisfied', 'label_fil' => 'Nasiyahan'],
                            ['value' => '4', 'score' => 4, 'label' => 'Very Satisfied', 'label_fil' => 'Lubos na Nasiyahan'],
                            ['value' => 'not_aware', 'label' => 'Not aware of available L&D opportunities', 'label_fil' => 'Hindi pamilyar sa mga inialok na L&D opportunity'],
                        ],
                    ],
                    [
                        'key' => 'barriers',
                        'type' => 'multiple',
                        'max' => 2,
                        'question' => 'What are the biggest challenges that prevent you from participating in or completing L&D activities?',
                        'question_fil' => 'Ano ang mga pangunahing hadlang sa inyong paglahok o pagkumpleto ng mga L&D activity?',
                        'instruction' => 'Select up to 2.',
                        'instruction_fil' => 'Pumili ng hanggang 2.',
                        'allow_other' => true,
                        'options' => [
                            ['value' => 'workload', 'label' => 'Heavy workload and competing deadlines', 'label_fil' => 'Mabigat na trabaho at magkakasabay na deadline'],
                            ['value' => 'no_learning_time', 'label' => 'Lack of dedicated learning time', 'label_fil' => 'Kakulangan ng nakalaang oras para sa pagkatuto'],
                            ['value' => 'schedule_conflict', 'label' => 'Training schedules conflicting with work responsibilities', 'label_fil' => 'Hindi tugmang iskedyul ng pagsasanay at trabaho'],
                            ['value' => 'irrelevant_topics', 'label' => 'Learning topics that are not relevant to my needs', 'label_fil' => 'Mga paksang hindi angkop sa aking pangangailangan'],
                            ['value' => 'limited_access', 'label' => 'Limited access to technology or learning resources', 'label_fil' => 'Limitadong access sa teknolohiya o learning resources'],
                            ['value' => 'low_support', 'label' => 'Insufficient support from my supervisor or office', 'label_fil' => 'Kakulangan ng suporta mula sa superbisor o opisina'],
                            ['value' => 'lack_of_information', 'label' => 'Lack of information about available L&D programs', 'label_fil' => 'Kakulangan ng impormasyon tungkol sa mga L&D program'],
                            ['value' => 'none', 'label' => 'No significant barriers', 'label_fil' => 'Walang malaking hadlang', 'exclusive' => true],
                        ],
                    ],
                    [
                        'key' => 'supervisor_support',
                        'type' => 'single',
                        'question' => 'How would you rate the support provided by your immediate supervisor or office for your participation in L&D activities?',
                        'question_fil' => 'Paano ninyo bibigyang-marka ang suportang ibinibigay ng inyong agarang superbisor o opisina sa inyong paglahok sa mga L&D activity?',
                        'options' => [
                            ...$poorToExcellent,
                            ['value' => 'not_applicable', 'label' => 'Not Applicable', 'label_fil' => 'Hindi Naaangkop'],
                        ],
                    ],
                    [
                        'key' => 'improvement_area',
                        'type' => 'single',
                        'question' => 'Which aspect of the L&D activities you attended during CY 2026 needs the most improvement?',
                        'question_fil' => 'Aling bahagi ng mga L&D activity na inyong nadaluhan noong 2026 ang higit na nangangailangan ng pagpapabuti?',
                        'allow_other' => true,
                        'options' => [
                            ['value' => 'topic_relevance', 'label' => 'Relevance of training topics', 'label_fil' => 'Pagiging angkop ng mga paksa sa trabaho'],
                            ['value' => 'trainers', 'label' => 'Quality of trainers or resource persons', 'label_fil' => 'Kakayahan ng mga tagapagsanay o resource person'],
                            ['value' => 'methods_engagement', 'label' => 'Learning methods and participant engagement', 'label_fil' => 'Mga paraan ng pagtuturo at pakikilahok'],
                            ['value' => 'schedule_duration', 'label' => 'Training schedules and duration', 'label_fil' => 'Iskedyul at haba ng pagsasanay'],
                            ['value' => 'materials_facilities', 'label' => 'Learning materials, facilities, and technology', 'label_fil' => 'Mga materyales, pasilidad, at teknolohiya'],
                            ['value' => 'application_at_work', 'label' => 'Opportunities to apply learning at work', 'label_fil' => 'Pagkakataong magamit ang natutuhan sa trabaho'],
                            ['value' => 'none', 'label' => 'No significant improvements needed', 'label_fil' => 'Walang malaking kailangang baguhin'],
                            ['value' => 'did_not_attend', 'label' => 'I did not attend any L&D activities', 'label_fil' => 'Wala akong nadaluhang L&D activity'],
                        ],
                    ],
                    [
                        'key' => 'suggestion',
                        'type' => 'text',
                        'required' => false,
                        'question' => "If you could suggest one improvement to TESDA's learning and development programs for CY 2027, what would it be?",
                        'question_fil' => 'Kung maaari kayong magmungkahi ng isang pagbabago upang mapahusay ang mga L&D program ng TESDA para sa 2027, ano ito?',
                    ],
                    [
                        'key' => 'overall_rating',
                        'type' => 'single',
                        'question' => 'Overall, how would you rate your learning and development experience during CY 2026?',
                        'question_fil' => 'Sa kabuuan, paano ninyo bibigyang-marka ang inyong karanasan sa mga L&D activity (Training, Conferences, Coaching and Mentoring etc) noong 2026?',
                        'options' => [
                            ...$poorToExcellent,
                            ['value' => 'did_not_attend', 'label' => 'I did not attend any L&D activities', 'label_fil' => 'Wala akong nadaluhang L&D activity'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'LinkedIn Learning',
                'step_label' => 'LinkedIn',
                'description' => 'Your experience with LinkedIn Learning.',
                'min_salary_grade' => 18,
                'questions' => [
                    [
                        'key' => 'linkedin_appreciation',
                        'type' => 'multiple',
                        'max' => 2,
                        'question' => 'What do you appreciate most about LinkedIn Learning?',
                        'question_fil' => 'Ano ang pinakanagustuhan ninyo sa paggamit ng LinkedIn Learning?',
                        'instruction' => 'Select up to two (2) answers.',
                        'instruction_fil' => 'Pumili ng hanggang dalawang (2) sagot.',
                        'allow_other' => true,
                        'options' => [
                            ['value' => 'course_variety', 'label' => 'Wide variety of courses and learning topics', 'label_fil' => 'Malawak na pagpipilian ng mga kurso at paksa'],
                            ['value' => 'flexibility', 'label' => 'Flexibility to learn anytime and at my own pace', 'label_fil' => 'Kalayaang matuto anumang oras at sa sariling bilis'],
                            ['value' => 'instructors', 'label' => 'Quality and expertise of instructors', 'label_fil' => 'Kalidad at kahusayan ng mga tagapagturo'],
                            ['value' => 'short_lessons', 'label' => 'Short and easy-to-follow video lessons', 'label_fil' => 'Maiikli at madaling sundan na video lesson'],
                            ['value' => 'course_relevance', 'label' => 'Relevance of courses to my work and career goals', 'label_fil' => 'Pagiging angkop ng mga kurso sa trabaho at karera'],
                            ['value' => 'certificates', 'label' => 'Certificates upon course completion', 'label_fil' => 'Mga sertipiko matapos makumpleto ang kurso'],
                            ['value' => 'ease_of_use', 'label' => 'Ease of accessing and navigating the platform', 'label_fil' => 'Dali ng pag-access at paggamit ng platform'],
                        ],
                    ],
                    [
                        'key' => 'linkedin_difficulties',
                        'type' => 'multiple',
                        'max' => 2,
                        'question' => 'What difficulties have you encountered when using LinkedIn Learning?',
                        'question_fil' => 'Anong mga hamon o suliranin ang inyong naranasan sa paggamit ng LinkedIn Learning?',
                        'instruction' => 'Select up to two (2) answers.',
                        'instruction_fil' => 'Pumili ng hanggang dalawang (2) sagot.',
                        'allow_other' => true,
                        'options' => [
                            ['value' => 'limited_time', 'label' => 'Limited time due to workload and competing priorities', 'label_fil' => 'Kakulangan ng oras dahil sa trabaho at iba pang prayoridad'],
                            ['value' => 'finding_courses', 'label' => 'Difficulty finding courses relevant to my needs', 'label_fil' => 'Hirap sa paghahanap ng mga kursong angkop sa aking pangangailangan'],
                            ['value' => 'technical_issues', 'label' => 'Technical difficulties or internet connectivity issues', 'label_fil' => 'Problemang teknikal o sa koneksiyon ng internet'],
                            ['value' => 'navigation', 'label' => 'Difficulty navigating or using the platform', 'label_fil' => 'Hirap sa paggamit o pag-navigate sa platform'],
                            ['value' => 'low_motivation', 'label' => 'Limited motivation to complete online courses', 'label_fil' => 'Kakulangan ng motibasyong tapusin ang mga online course'],
                            ['value' => 'no_time_or_support', 'label' => 'Lack of dedicated learning time or supervisor support', 'label_fil' => 'Kakulangan ng nakalaang oras o suporta mula sa superbisor'],
                            ['value' => 'none', 'label' => 'No significant difficulties encountered', 'label_fil' => 'Walang malaking suliraning naranasan', 'exclusive' => true],
                        ],
                    ],
                    [
                        'key' => 'linkedin_rating',
                        'type' => 'single',
                        'question' => 'Overall, how would you rate your experience with LinkedIn Learning during CY 2026?',
                        'question_fil' => 'Sa kabuuan, paano ninyo bibigyang-marka ang inyong karanasan sa paggamit ng LinkedIn Learning noong 2026?',
                        'options' => $poorToExcellent,
                    ],
                    [
                        'key' => 'linkedin_recommend',
                        'type' => 'single',
                        'question' => 'Would you recommend LinkedIn Learning to other TESDA personnel?',
                        'options' => [
                            ['value' => 'yes', 'label' => 'Yes'],
                            ['value' => 'no', 'label' => 'No'],
                        ],
                    ],
                ],
            ],
        ];
    }
};
