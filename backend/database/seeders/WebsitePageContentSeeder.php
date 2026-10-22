<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use Illuminate\Database\Seeder;

/**
 * Seeds the admin-editable copy for the shared header/footer and for every
 * public page (home, services, team, gallery, news, contact). The values match
 * the design defaults already hardcoded in the website so the CMS starts out
 * pixel-identical and every string can then be changed from the admin.
 * Idempotent: existing blocks keep their admin-edited values.
 */
class WebsitePageContentSeeder extends Seeder
{
    /**
     * Short clinic description reused by the homepage about section and the
     * footer blurb. Both are separate blocks so they can be edited apart.
     */
    private const CLINIC_BLURB = 'RoyalCare Clinic brings together experienced clinicians, modern facilities and a patient-first approach to deliver care you and your family can trust.';

    public function run(): void
    {
        $blocks = [
            // Shared header (logo/branding live in website settings).
            ['key' => 'header_cta_label', 'group' => 'header', 'body' => 'Book an Appointment'],
            ['key' => 'header_nav_home', 'group' => 'header', 'body' => 'Home'],
            ['key' => 'header_nav_about', 'group' => 'header', 'body' => 'About Us'],
            ['key' => 'header_nav_services', 'group' => 'header', 'body' => 'Services'],
            ['key' => 'header_nav_news', 'group' => 'header', 'body' => 'News & Health'],
            ['key' => 'header_nav_contact', 'group' => 'header', 'body' => 'Contact Us'],
            ['key' => 'header_nav_team', 'group' => 'header', 'body' => 'Our Team'],
            ['key' => 'header_nav_gallery', 'group' => 'header', 'body' => 'Gallery'],

            // Shared footer.
            ['key' => 'footer_quick_links_heading', 'group' => 'footer', 'body' => 'Quick Links'],
            ['key' => 'footer_services_heading', 'group' => 'footer', 'body' => 'Our Services'],
            ['key' => 'footer_contact_heading', 'group' => 'footer', 'body' => 'Get in Touch'],
            ['key' => 'footer_services_link', 'group' => 'footer', 'body' => 'View our services'],
            ['key' => 'footer_rights_text', 'group' => 'footer', 'body' => 'All rights reserved.'],
            ['key' => 'footer_about_text', 'group' => 'footer', 'body' => self::CLINIC_BLURB],

            // Home — hero copy. The tagline itself stays in Website Settings
            // because it is used across every page.
            ['key' => 'home_hero_title', 'group' => 'home', 'body' => ''],
            ['key' => 'home_hero_subtitle', 'group' => 'home', 'body' => 'At RoyalCare Clinic, we provide compassionate, high-quality healthcare with modern facilities and expert medical professionals.'],
            ['key' => 'home_hero_highlight', 'group' => 'home', 'body' => 'Open 24 hours a day, 7 days a week — your health never waits.'],
            ['key' => 'home_hero_image', 'group' => 'home', 'title' => 'Homepage hero image'],
            ['key' => 'home_hero_primary_link', 'group' => 'home', 'body' => 'Explore Our Services'],
            ['key' => 'home_hero_secondary_link', 'group' => 'home', 'body' => 'Book an Appointment'],
            ['key' => 'home_stats', 'group' => 'home', 'title' => 'Hero statistics', 'data' => [
                ['title' => 'Trusted by', 'value' => '10,000+', 'label' => 'Happy Patients'],
                ['title' => 'Expert Doctors', 'value' => '50+', 'label' => 'Specialists'],
                ['title' => 'Open Daily', 'value' => '24 Hours', 'label' => 'Your Health, Always'],
            ]],

            // Home — partners strip.
            ['key' => 'home_partners_heading', 'group' => 'home', 'body' => "Trusted by Leading\nPartners"],
            ['key' => 'home_partners', 'group' => 'home', 'title' => 'Partners', 'data' => [
                ['title' => 'PharmaHealth', 'text' => 'leaf'],
                ['title' => 'MediCare', 'text' => 'plus'],
                ['title' => 'LifePlus', 'text' => 'heart'],
                ['title' => 'HealthFirst', 'text' => 'leaf'],
                ['title' => 'WellnessCo', 'text' => 'wave'],
            ]],

            // Home — about section.
            ['key' => 'home_about_eyebrow', 'group' => 'home', 'body' => 'About RoyalCare'],
            ['key' => 'home_about_heading', 'group' => 'home', 'body' => 'Compassionate Care, Advanced Medicine'],
            ['key' => 'home_about_text', 'group' => 'home', 'body' => self::CLINIC_BLURB],
            ['key' => 'home_about_pillars', 'group' => 'home', 'title' => 'Highlight points', 'data' => [
                ['title' => 'Patient-first, respectful care'],
                ['title' => 'Experienced clinical team'],
                ['title' => 'Modern, comfortable facilities'],
                ['title' => 'Clear communication at every step'],
            ]],
            ['key' => 'home_about_link', 'group' => 'home', 'body' => 'More about us'],

            // Home — content sections.
            ['key' => 'home_services_eyebrow', 'group' => 'home', 'body' => 'What we offer'],
            ['key' => 'home_services_heading', 'group' => 'home', 'body' => 'Our Medical Services'],
            ['key' => 'home_services_link', 'group' => 'home', 'body' => 'View all services'],
            ['key' => 'home_team_eyebrow', 'group' => 'home', 'body' => 'Meet the experts'],
            ['key' => 'home_team_heading', 'group' => 'home', 'body' => 'Our Team'],
            ['key' => 'home_team_link', 'group' => 'home', 'body' => 'View all team'],
            ['key' => 'home_news_eyebrow', 'group' => 'home', 'body' => 'Stay informed'],
            ['key' => 'home_news_heading', 'group' => 'home', 'body' => 'News & Health Tips'],
            ['key' => 'home_news_link', 'group' => 'home', 'body' => 'Read all articles'],
            ['key' => 'home_gallery_eyebrow', 'group' => 'home', 'body' => 'Our facilities'],
            ['key' => 'home_gallery_heading', 'group' => 'home', 'body' => 'A Glimpse Inside'],
            ['key' => 'home_gallery_link', 'group' => 'home', 'body' => 'View full gallery'],

            // Home — closing call-to-action banner.
            ['key' => 'home_cta_heading', 'group' => 'home', 'body' => 'Ready to visit us?'],
            ['key' => 'home_cta_text', 'group' => 'home', 'body' => 'Reach out to schedule an appointment or ask us anything about our services.'],
            ['key' => 'home_cta_contact_label', 'group' => 'home', 'body' => 'Contact Us'],
            ['key' => 'home_cta_image', 'group' => 'home', 'title' => 'Call-to-action banner background'],

            // Services page.
            ['key' => 'services_eyebrow', 'group' => 'services', 'body' => 'What We Offer'],
            ['key' => 'services_heading', 'group' => 'services', 'body' => 'Our Services'],
            ['key' => 'services_intro', 'group' => 'services', 'body' => 'Comprehensive medical care delivered by experienced professionals.'],
            ['key' => 'services_featured_eyebrow', 'group' => 'services', 'body' => '01 — Spotlight'],
            ['key' => 'services_featured_heading', 'group' => 'services', 'body' => 'Services We Highlight'],
            ['key' => 'services_featured_text', 'group' => 'services', 'body' => 'A closer look at the services featured across our clinic.'],
            ['key' => 'services_catalogue_eyebrow', 'group' => 'services', 'body' => '02 — Full Catalogue'],
            ['key' => 'services_catalogue_heading', 'group' => 'services', 'body' => 'All Services'],
            ['key' => 'services_catalogue_text', 'group' => 'services', 'body' => 'Browse the full list — select a service to read more.'],
            ['key' => 'services_closing_heading', 'group' => 'services', 'body' => 'Not Sure Where to Start?'],
            ['key' => 'services_closing_text', 'group' => 'services', 'body' => 'Tell us what you are looking for and our team will help you find the right service for you and your family.'],

            // Team page.
            ['key' => 'team_eyebrow', 'group' => 'team', 'body' => 'Get to Know Us'],
            ['key' => 'team_heading', 'group' => 'team', 'body' => 'Our Team'],
            ['key' => 'team_description', 'group' => 'team', 'body' => 'Meet the dedicated professionals committed to your health and wellbeing.'],
            ['key' => 'team_people_eyebrow', 'group' => 'team', 'body' => '01 — The People'],
            ['key' => 'team_people_heading', 'group' => 'team', 'body' => 'The People Behind Your Care'],
            ['key' => 'team_people_text', 'group' => 'team', 'body' => 'Meet the dedicated professionals committed to providing compassionate, patient-focused healthcare.'],
            ['key' => 'team_closing_heading', 'group' => 'team', 'body' => 'Book With Our Team'],
            ['key' => 'team_closing_text', 'group' => 'team', 'body' => 'Choose a service and reach out — we will help you find the right care for you and your family.'],

            // Gallery page.
            ['key' => 'gallery_eyebrow', 'group' => 'gallery', 'body' => 'Inside Our Clinic'],
            ['key' => 'gallery_heading', 'group' => 'gallery', 'body' => 'Facilities & Gallery'],
            ['key' => 'gallery_description', 'group' => 'gallery', 'body' => 'Take a look at our clinic, our spaces and the people behind your care.'],
            ['key' => 'gallery_section_eyebrow', 'group' => 'gallery', 'body' => '01 — The Gallery'],
            ['key' => 'gallery_section_heading', 'group' => 'gallery', 'body' => 'Inside the Clinic'],
            ['key' => 'gallery_section_help', 'group' => 'gallery', 'body' => 'Tap any photo to view it full size.'],

            // News page.
            ['key' => 'news_eyebrow', 'group' => 'news', 'body' => 'From Our Medical Team'],
            ['key' => 'news_heading', 'group' => 'news', 'body' => 'News & Health'],
            ['key' => 'news_description', 'group' => 'news', 'body' => 'Clinic updates, health tips and articles from our medical team.'],
            ['key' => 'news_latest_heading', 'group' => 'news', 'body' => 'Latest Stories'],
            ['key' => 'news_closing_heading', 'group' => 'news', 'body' => 'Have a Health Question?'],
            ['key' => 'news_closing_text', 'group' => 'news', 'body' => 'Our team is happy to talk through anything you have read — or help you find the right service for you and your family.'],

            // Contact page.
            ['key' => 'contact_eyebrow', 'group' => 'contact', 'body' => 'Get in Touch'],
            ['key' => 'contact_heading', 'group' => 'contact', 'body' => 'Contact Us'],
            ['key' => 'contact_description', 'group' => 'contact', 'body' => 'We are here to help. Reach out to us through any of the channels below — we’ll respond as quickly as we can.'],
            ['key' => 'contact_reach_heading', 'group' => 'contact', 'body' => 'Ways to Reach Us'],
            ['key' => 'contact_reach_text', 'group' => 'contact', 'body' => 'Pick whichever is easiest — every channel reaches the same RoyalCare Clinic team.'],
            ['key' => 'contact_find_heading', 'group' => 'contact', 'body' => 'Visit Our Clinic'],
            ['key' => 'contact_send_heading', 'group' => 'contact', 'body' => 'Send Us a Message'],
            ['key' => 'contact_send_text', 'group' => 'contact', 'body' => 'Fill out the form and your message goes straight to our clinic team — or use any of the channels above.'],
            ['key' => 'contact_send_note', 'group' => 'contact', 'body' => "We'll get back to you as soon as we can."],
        ];

        foreach ($blocks as $block) {
            ContentBlock::firstOrCreate(
                ['key' => $block['key'], 'group' => $block['group']],
                $block
            );
        }
    }
}
