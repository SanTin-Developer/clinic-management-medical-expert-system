/**
 * Registry of admin-editable website content blocks.
 *
 * Each group maps to one tab of the "Page Content" screen and to the `group`
 * column of the `content_blocks` table (see WebsitePageContentSeeder /
 * AboutContentBlockSeeder). Field definitions are the single source of truth
 * for what the admin can edit; the public website reads the same keys and
 * falls back to its design defaults whenever a block is empty.
 *
 * Field types:
 *  - 'text'     single-line input          (body column)
 *  - 'textarea' multi-line input           (body column)
 *  - 'list'     repeatable items           (data JSON column, `listFields`)
 *  - 'image'    uploaded picture           (image_media_id column)
 */
export const CONTENT_GROUPS = [
  {
    id: 'home',
    label: 'Home Page',
    description: 'The homepage hero, statistics, partners, about section, section headings and closing banner. Site name, tagline, logo and contact details come from Website Settings.',
    cards: [
      {
        title: 'Hero',
        hint: 'The headline, subtitle, callout and photo at the top of the homepage.',
        fields: [
          { key: 'home_hero_title', label: 'Headline', hint: 'Large title shown over the hero photo. Leave empty to keep the two-line design headline.' },
          { key: 'home_hero_subtitle', label: 'Subtitle', type: 'textarea', rows: 3, hint: 'Paragraph shown under the headline.' },
          { key: 'home_hero_highlight', label: 'Highlight callout', type: 'textarea', rows: 2, hint: 'Green callout card shown under the subtitle.' },
          { key: 'home_hero_image', label: 'Hero photo', type: 'image', hint: 'Full-width banner photo behind the hero copy. Leave empty to keep the design default.' },
        ],
      },
      {
        title: 'Hero buttons',
        fields: [
          { key: 'home_hero_primary_link', label: 'Primary button', hint: 'Green button linking to the services page.' },
          { key: 'home_hero_secondary_link', label: 'Secondary button', hint: 'Outlined button linking to the contact page.' },
        ],
      },
      {
        title: 'Statistics',
        fields: [
          {
            key: 'home_stats',
            label: 'Hero statistic cards',
            type: 'list',
            hint: 'Shown floating beside the hero on desktop and as a strip on phones.',
            listFields: [
              { key: 'title', label: 'Label', placeholder: 'Trusted by' },
              { key: 'value', label: 'Value', placeholder: '10,000+' },
              { key: 'label', label: 'Caption', placeholder: 'Happy Patients' },
            ],
            addLabel: 'Add statistic',
          },
        ],
      },
      {
        title: 'Partners strip',
        fields: [
          { key: 'home_partners_heading', label: 'Heading', type: 'textarea', rows: 2, hint: 'Use a new line to break the heading onto two lines.' },
          {
            key: 'home_partners',
            label: 'Partners',
            type: 'list',
            listFields: [
              { key: 'title', label: 'Name', placeholder: 'PharmaHealth' },
              { key: 'text', label: 'Glyph', placeholder: 'leaf / plus / heart / wave' },
            ],
            addLabel: 'Add partner',
          },
        ],
      },
      {
        title: 'About section',
        fields: [
          { key: 'home_about_eyebrow', label: 'Eyebrow', hint: 'Small label above the heading.' },
          { key: 'home_about_heading', label: 'Heading' },
          { key: 'home_about_text', label: 'Description', type: 'textarea', rows: 4, hint: 'Paragraph shown under the heading.' },
          {
            key: 'home_about_pillars',
            label: 'Highlight points',
            type: 'list',
            hint: 'The checklist beside the about image.',
            listFields: [{ key: 'title', label: 'Point', placeholder: 'Patient-first, respectful care' }],
            addLabel: 'Add point',
          },
          { key: 'home_about_link', label: 'Button label', hint: 'Button linking to the About page.' },
        ],
      },
      {
        title: 'Section headings',
        fields: [
          { key: 'home_services_eyebrow', label: 'Services eyebrow' },
          { key: 'home_services_heading', label: 'Services heading' },
          { key: 'home_services_link', label: 'Services link' },
          { key: 'home_team_eyebrow', label: 'Team eyebrow' },
          { key: 'home_team_heading', label: 'Team heading' },
          { key: 'home_team_link', label: 'Team link' },
          { key: 'home_news_eyebrow', label: 'News eyebrow' },
          { key: 'home_news_heading', label: 'News heading' },
          { key: 'home_news_link', label: 'News link' },
          { key: 'home_gallery_eyebrow', label: 'Gallery eyebrow' },
          { key: 'home_gallery_heading', label: 'Gallery heading' },
          { key: 'home_gallery_link', label: 'Gallery link' },
        ],
      },
      {
        title: 'Closing banner',
        fields: [
          { key: 'home_cta_heading', label: 'Heading' },
          { key: 'home_cta_text', label: 'Description', type: 'textarea', rows: 2 },
          { key: 'home_cta_contact_label', label: 'Button label' },
          { key: 'home_cta_image', label: 'Banner background', type: 'image', hint: 'Photo behind the dark overlay. Leave empty to keep the design default.' },
        ],
      },
    ],
  },
  {
    id: 'header',
    label: 'Header',
    description: 'The navigation menu and appointment button shown at the top of every page. The logo and site name are edited in Website Settings.',
    cards: [
      {
        title: 'Appointment button',
        fields: [{ key: 'header_cta_label', label: 'Button label', hint: 'Shown in the top-right corner and in the mobile menu.' }],
      },
      {
        title: 'Menu labels',
        fields: [
          { key: 'header_nav_home', label: 'Home' },
          { key: 'header_nav_about', label: 'About Us' },
          { key: 'header_nav_services', label: 'Services' },
          { key: 'header_nav_news', label: 'News & Health' },
          { key: 'header_nav_contact', label: 'Contact Us' },
          { key: 'header_nav_team', label: 'Our Team (footer)' },
          { key: 'header_nav_gallery', label: 'Gallery (footer)' },
        ],
      },
    ],
  },
  {
    id: 'footer',
    label: 'Footer',
    description: 'Column headings, about text and the copyright line of the footer shown on every page. Contact details and social links come from Website Settings.',
    cards: [
      {
        title: 'About text',
        fields: [
          { key: 'footer_about_text', label: 'Short description', type: 'textarea', rows: 3, hint: 'Shown under the tagline in the footer. Leave empty to hide it.' },
        ],
      },
      {
        title: 'Column headings',
        fields: [
          { key: 'footer_quick_links_heading', label: 'Quick links heading' },
          { key: 'footer_services_heading', label: 'Services heading' },
          { key: 'footer_contact_heading', label: 'Contact heading' },
          { key: 'footer_services_link', label: 'Services fallback link', hint: 'Shown when no services are published yet.' },
        ],
      },
      {
        title: 'Copyright',
        fields: [{ key: 'footer_rights_text', label: 'Rights text', hint: 'Appended after the site name, e.g. “All rights reserved.”' }],
      },
    ],
  },
  {
    id: 'services',
    label: 'Services Page',
    description: 'Headings and descriptions on the public Services page.',
    cards: [
      {
        title: 'Page hero',
        fields: [
          { key: 'services_eyebrow', label: 'Eyebrow' },
          { key: 'services_heading', label: 'Heading', hint: 'The last word renders in green.' },
          { key: 'services_intro', label: 'Introduction', type: 'textarea', rows: 2 },
        ],
      },
      {
        title: 'Spotlight section',
        fields: [
          { key: 'services_featured_eyebrow', label: 'Eyebrow' },
          { key: 'services_featured_heading', label: 'Heading' },
          { key: 'services_featured_text', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
      {
        title: 'Catalogue section',
        fields: [
          { key: 'services_catalogue_eyebrow', label: 'Eyebrow' },
          { key: 'services_catalogue_heading', label: 'Heading' },
          { key: 'services_catalogue_text', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
      {
        title: 'Closing banner',
        fields: [
          { key: 'services_closing_heading', label: 'Heading' },
          { key: 'services_closing_text', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
    ],
  },
  {
    id: 'team',
    label: 'Team Page',
    description: 'Headings and descriptions on the public Team page. Team members themselves are managed under “Our Team”.',
    cards: [
      {
        title: 'Page hero',
        fields: [
          { key: 'team_eyebrow', label: 'Eyebrow' },
          { key: 'team_heading', label: 'Heading', hint: 'The last word renders in green.' },
          { key: 'team_description', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
      {
        title: 'Team list section',
        fields: [
          { key: 'team_people_eyebrow', label: 'Eyebrow' },
          { key: 'team_people_heading', label: 'Heading' },
          { key: 'team_people_text', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
      {
        title: 'Closing banner',
        fields: [
          { key: 'team_closing_heading', label: 'Heading' },
          { key: 'team_closing_text', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
    ],
  },
  {
    id: 'gallery',
    label: 'Gallery Page',
    description: 'Headings and helper text on the public Gallery page. Photos are managed under “Gallery”.',
    cards: [
      {
        title: 'Page hero',
        fields: [
          { key: 'gallery_eyebrow', label: 'Eyebrow' },
          { key: 'gallery_heading', label: 'Heading', hint: 'The last word renders in green.' },
          { key: 'gallery_description', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
      {
        title: 'Photo grid',
        fields: [
          { key: 'gallery_section_eyebrow', label: 'Eyebrow' },
          { key: 'gallery_section_heading', label: 'Heading' },
          { key: 'gallery_section_help', label: 'Helper text' },
        ],
      },
    ],
  },
  {
    id: 'news',
    label: 'News Page',
    description: 'Headings and descriptions on the public News & Health page. Articles are managed under “News & Health”.',
    cards: [
      {
        title: 'Page hero',
        fields: [
          { key: 'news_eyebrow', label: 'Eyebrow' },
          { key: 'news_heading', label: 'Heading', hint: 'The last word renders in green.' },
          { key: 'news_description', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
      {
        title: 'Article list',
        fields: [{ key: 'news_latest_heading', label: 'Heading' }],
      },
      {
        title: 'Closing banner',
        fields: [
          { key: 'news_closing_heading', label: 'Heading' },
          { key: 'news_closing_text', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
    ],
  },
  {
    id: 'contact',
    label: 'Contact Page',
    description: 'Headings and descriptions on the public Contact page. Phone, email, address and hours come from Website Settings.',
    cards: [
      {
        title: 'Page hero',
        fields: [
          { key: 'contact_eyebrow', label: 'Eyebrow' },
          { key: 'contact_heading', label: 'Heading', hint: 'The last word renders in green.' },
          { key: 'contact_description', label: 'Description', type: 'textarea', rows: 2 },
        ],
      },
      {
        title: 'Reach us section',
        fields: [
          { key: 'contact_reach_heading', label: 'Heading' },
          { key: 'contact_reach_text', label: 'Description', type: 'textarea', rows: 2 },
          { key: 'contact_find_heading', label: 'Map card heading' },
        ],
      },
      {
        title: 'Contact form',
        fields: [
          { key: 'contact_send_heading', label: 'Heading' },
          { key: 'contact_send_text', label: 'Description', type: 'textarea', rows: 2 },
          { key: 'contact_send_note', label: 'Note under the form' },
        ],
      },
    ],
  },
  {
    id: 'about',
    label: 'About Page',
    description: 'Edit the text and image shown on the public About page. The design and layout stay the same.',
    cards: [
      {
        title: 'Hero',
        fields: [
          { key: 'about_hero_badge', label: 'Badge' },
          { key: 'about_hero_title', label: 'Hero title' },
          { key: 'about_hero_subtitle', label: 'Hero subtitle', type: 'textarea', rows: 3 },
          { key: 'about_hero_primary_link', label: 'Primary button' },
          { key: 'about_hero_secondary_link', label: 'Secondary button' },
        ],
      },
      {
        title: 'Section Links',
        hint: 'Labels of the quick-jump chips shown in the hero.',
        fields: [
          { key: 'about_nav_story_label', label: 'Our Story' },
          { key: 'about_nav_mission_label', label: 'Mission & Vision' },
          { key: 'about_nav_values_label', label: 'Core Values' },
          { key: 'about_nav_facilities_label', label: 'Our Facilities' },
          { key: 'about_nav_team_label', label: 'Our Team' },
        ],
      },
      {
        title: 'Who We Are',
        fields: [
          { key: 'about_story_eyebrow', label: 'Eyebrow', hint: 'Small label above the heading, e.g. “01 — Who We Are”.' },
          { key: 'about_story_heading', label: 'Heading' },
          { key: 'about_story_subheading', label: 'Subheading', type: 'textarea', rows: 3 },
          { key: 'about_story_note', label: 'Image note', hint: 'Optional note shown on the card overlapping the photo.' },
          { key: 'about_story_image', label: 'Image', type: 'image', hint: 'Photo shown next to the Who We Are text.' },
        ],
      },
      {
        title: 'Mission & Vision',
        fields: [
          { key: 'about_mission_eyebrow', label: 'Eyebrow', hint: 'Small label above the heading, e.g. “02 — Our Direction”.' },
          { key: 'about_mission_heading', label: 'Section heading' },
          { key: 'about_mission_title', label: 'Mission title' },
          { key: 'about_mission_text', label: 'Mission text', type: 'textarea', rows: 3 },
          { key: 'about_vision_title', label: 'Vision title' },
          { key: 'about_vision_text', label: 'Vision text', type: 'textarea', rows: 3 },
        ],
      },
      {
        title: 'Core Values',
        fields: [
          { key: 'about_values_eyebrow', label: 'Eyebrow', hint: 'Small label above the heading, e.g. “03 — What Guides Us”.' },
          { key: 'about_values_heading', label: 'Heading' },
          { key: 'about_values_subheading', label: 'Subheading', type: 'textarea', rows: 2 },
          {
            key: 'about_values',
            label: 'Value cards',
            type: 'list',
            hint: 'The first card is highlighted in the layout.',
            listFields: [
              { key: 'title', label: 'Title', placeholder: 'Title' },
              { key: 'text', label: 'Description', placeholder: 'Description', multiline: true },
            ],
            addLabel: 'Add value',
          },
        ],
      },
      {
        title: 'Facilities',
        fields: [
          { key: 'about_facilities_eyebrow', label: 'Eyebrow', hint: 'Small label above the heading, e.g. “04 — Our Facilities”.' },
          { key: 'about_facilities_heading', label: 'Heading' },
          { key: 'about_facilities_text', label: 'Description', type: 'textarea', rows: 2 },
          { key: 'about_facilities_link', label: 'Link label', hint: 'Button linking to the full gallery.' },
        ],
      },
      {
        title: 'Our Team',
        fields: [
          { key: 'about_team_eyebrow', label: 'Eyebrow', hint: 'Small label above the heading, e.g. “05 — Our People”.' },
          { key: 'about_team_heading', label: 'Heading' },
          { key: 'about_team_text', label: 'Description', type: 'textarea', rows: 2 },
          { key: 'about_team_link', label: 'Link label', hint: 'Link shown below the team cards.' },
        ],
      },
      {
        title: 'Our Commitment',
        fields: [
          { key: 'about_commitment_eyebrow', label: 'Eyebrow', hint: 'Small label above the heading, e.g. “06 — Our Commitment”.' },
          { key: 'about_commitment_heading', label: 'Heading' },
          { key: 'about_commitment_subheading', label: 'Subheading', type: 'textarea', rows: 2 },
          {
            key: 'about_commitments',
            label: 'Commitment items',
            type: 'list',
            listFields: [
              { key: 'title', label: 'Title', placeholder: 'Title' },
              { key: 'text', label: 'Description', placeholder: 'Description', multiline: true },
            ],
            addLabel: 'Add item',
          },
        ],
      },
      {
        title: 'Call to Action',
        fields: [
          { key: 'about_cta_eyebrow', label: 'Eyebrow', hint: 'Small label above the heading, e.g. “07 — Next Steps”.' },
          { key: 'about_cta_heading', label: 'Heading' },
          { key: 'about_cta_subheading', label: 'Subheading', type: 'textarea', rows: 3 },
          { key: 'about_cta_primary_link', label: 'Primary button' },
          { key: 'about_cta_secondary_link', label: 'Secondary button' },
        ],
      },
    ],
  },
]

/** All editable field definitions of a group, flattened. */
export function groupFields(group) {
  return group.cards.flatMap((card) => card.fields)
}
