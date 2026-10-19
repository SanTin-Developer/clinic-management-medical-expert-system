<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use Illuminate\Database\Seeder;

class AboutContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            ['key' => 'about_hero_badge', 'group' => 'about', 'body' => 'Advanced care, human touch.'],
            ['key' => 'about_hero_title', 'group' => 'about', 'body' => 'Caring for Your Health, Every Step of the Way'],
            ['key' => 'about_hero_subtitle', 'group' => 'about', 'body' => 'We’re committed to patient-centered care, clear communication, and empowering individuals and families to make informed decisions about their health and well-being.'],
            ['key' => 'about_hero_primary_link', 'group' => 'about', 'body' => 'Contact Us'],
            ['key' => 'about_hero_secondary_link', 'group' => 'about', 'body' => 'Explore Our Facilities'],

            ['key' => 'about_nav_story_label', 'group' => 'about', 'body' => 'Our Story'],
            ['key' => 'about_nav_mission_label', 'group' => 'about', 'body' => 'Mission & Vision'],
            ['key' => 'about_nav_values_label', 'group' => 'about', 'body' => 'Core Values'],
            ['key' => 'about_nav_facilities_label', 'group' => 'about', 'body' => 'Our Facilities'],
            ['key' => 'about_nav_team_label', 'group' => 'about', 'body' => 'Our Team'],

            ['key' => 'about_story_eyebrow', 'group' => 'about', 'body' => '01 — Who We Are'],
            ['key' => 'about_story_heading', 'group' => 'about', 'body' => 'Respectful Care. Clear Communication. Trusted Expertise.'],
            ['key' => 'about_story_subheading', 'group' => 'about', 'body' => 'More than a clinic: a team that listens, explains, and stays by your side through every step of your health journey.'],
            ['key' => 'about_story_note', 'group' => 'about', 'body' => 'Clear communication at every step'],
            ['key' => 'about_story_image', 'group' => 'about', 'title' => 'Who We Are image'],

            ['key' => 'about_mission_eyebrow', 'group' => 'about', 'body' => '02 — Our Direction'],
            ['key' => 'about_mission_heading', 'group' => 'about', 'body' => 'Mission & Vision'],
            ['key' => 'about_mission_title', 'group' => 'about', 'body' => 'Our Mission'],
            ['key' => 'about_mission_text', 'group' => 'about', 'body' => 'To provide compassionate, patient-centered care by supporting informed healthcare decisions through clear communication, respect, and accessible health information.'],
            ['key' => 'about_vision_title', 'group' => 'about', 'body' => 'Our Vision'],
            ['key' => 'about_vision_text', 'group' => 'about', 'body' => 'To create a healthcare experience where every person feels informed, respected, heard, and fully supported on their journey to better health.'],

            ['key' => 'about_values_eyebrow', 'group' => 'about', 'body' => '03 — What Guides Us'],
            ['key' => 'about_values_heading', 'group' => 'about', 'body' => 'Our Core Values'],
            ['key' => 'about_values_subheading', 'group' => 'about', 'body' => 'Five principles that shape every appointment, conversation and decision at RoyalCare.'],
            ['key' => 'about_values', 'group' => 'about', 'title' => 'Core values', 'data' => [
                ['title' => 'Patient-Centered Care', 'text' => 'Care shaped around each person’s needs, comfort and questions — before, during and after every visit.'],
                ['title' => 'Respect and Compassion', 'text' => 'Every person is treated with dignity, patience and genuine consideration.'],
                ['title' => 'Clear Communication', 'text' => 'Plain, honest explanations so you understand your options and next steps.'],
                ['title' => 'Privacy and Confidentiality', 'text' => 'Your personal details and health information are handled carefully and discreetly.'],
                ['title' => 'Responsible Healthcare Technology', 'text' => 'Modern tools are used thoughtfully to enhance care — never to replace the human connection.'],
            ]],

            ['key' => 'about_facilities_eyebrow', 'group' => 'about', 'body' => '04 — Our Facilities'],
            ['key' => 'about_facilities_heading', 'group' => 'about', 'body' => 'A Glimpse Inside'],
            ['key' => 'about_facilities_text', 'group' => 'about', 'body' => 'Take a look at our clinic, modern spaces, and the people committed to providing compassionate care.'],
            ['key' => 'about_facilities_link', 'group' => 'about', 'body' => 'View All Facilities'],

            ['key' => 'about_team_eyebrow', 'group' => 'about', 'body' => '05 — Our People'],
            ['key' => 'about_team_heading', 'group' => 'about', 'body' => 'The People Behind Your Care'],
            ['key' => 'about_team_text', 'group' => 'about', 'body' => 'Meet our dedicated team of healthcare professionals committed to your health, comfort, and wellbeing.'],
            ['key' => 'about_team_link', 'group' => 'about', 'body' => 'Meet Our Team'],

            ['key' => 'about_commitment_eyebrow', 'group' => 'about', 'body' => '06 — Our Commitment'],
            ['key' => 'about_commitment_heading', 'group' => 'about', 'body' => 'Our Commitment to Patients'],
            ['key' => 'about_commitment_subheading', 'group' => 'about', 'body' => 'How we intend to communicate, protect your privacy and support the decisions you make about your health.'],
            ['key' => 'about_commitments', 'group' => 'about', 'title' => 'Commitments', 'data' => [
                ['title' => 'Respectful patient communication', 'text' => 'We listen first and explain things in language that is easy to follow.'],
                ['title' => 'Appropriate privacy and confidentiality', 'text' => 'Your information is treated as private and safeguarded at every step.'],
                ['title' => 'Clear health information', 'text' => 'Straightforward guidance that helps you understand what matters for your wellbeing.'],
                ['title' => 'Informed decisions', 'text' => 'Your options are laid out clearly, so choices feel considered — never rushed.'],
            ]],

            ['key' => 'about_cta_eyebrow', 'group' => 'about', 'body' => '07 — Next Steps'],
            ['key' => 'about_cta_heading', 'group' => 'about', 'body' => 'We’re Here to Help'],
            ['key' => 'about_cta_subheading', 'group' => 'about', 'body' => 'Have questions about RoyalCare Clinic? Contact our team to learn more about our clinic, services, and how we can support your health journey.'],
            ['key' => 'about_cta_primary_link', 'group' => 'about', 'body' => 'Contact Us'],
            ['key' => 'about_cta_secondary_link', 'group' => 'about', 'body' => 'Explore Health Articles'],
        ];

        foreach ($blocks as $block) {
            ContentBlock::updateOrCreate(
                ['key' => $block['key'], 'group' => $block['group']],
                $block
            );
        }
    }
}
