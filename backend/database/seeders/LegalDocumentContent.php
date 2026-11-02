<?php

namespace Database\Seeders;

/**
 * The bilingual Privacy Policy and Terms of Use served on the public legal
 * routes. Kept out of the seeder so the long copy stays readable.
 *
 * Shape mirrors what PrivacyPolicyEditorModal sends to
 * PublicPage::$policy_content: per-language title, introduction and an ordered
 * list of sections. Plain text only — the public page renders it as text.
 */
final class LegalDocumentContent
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function pages(): array
    {
        return [
            self::privacyPolicy(),
            self::termsOfUse(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function privacyPolicy(): array
    {
        return [
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'title_km' => 'គោលការណ៍ឯកជនភាព | Privacy Policy',
            'excerpt' => 'How we collect, use and protect your information.',
            'excerpt_km' => 'របៀបដែលយើងប្រមូល ប្រើប្រាស់ និងការពារព័ត៌មានរបស់អ្នក។',
            'internal_notes' => <<<'EOT'
Before publishing, verify the actual website cookies and analytics, hosting provider, Cloudinary usage, external APIs, data storage locations, access controls, medical record retention requirements, and official clinic contact details. Review the policy against applicable Cambodian law. Do not publish until the unverified statements have been reviewed and approved.
EOT,
            'policy_content' => [
                'en' => [
                    'title' => 'Privacy Policy',
                    'introduction' => 'RoyalCare Clinic respects your privacy and is committed to handling your personal information responsibly. This Privacy Policy explains how we collect, use, store, and protect information when you visit our website or interact with our clinic services.',
                    'sections' => [
                        [
                            'order' => 1,
                            'visible' => true,
                            'heading' => '1. Who We Are',
                            'body' => <<<'EOT'
RoyalCare Clinic provides healthcare services and operates this public website to share information about our clinic, services, and health-related resources. RoyalCare Clinic is responsible for managing personal information handled through its website and clinic operations, subject to applicable requirements.

For questions about this Privacy Policy, please contact us through our Contact Us page or our verified official contact channels.
EOT,
                        ],
                        [
                            'order' => 2,
                            'visible' => true,
                            'heading' => '2. Information We Collect',
                            'body' => <<<'EOT'
Depending on how you interact with RoyalCare Clinic, we may collect personal information such as your name, email address, phone number, appointment request details, and messages submitted through our website.

When you receive healthcare services, relevant medical information may be processed as part of your care. This may include medical history, diagnoses, prescriptions, test results, and treatment records, where applicable.

The information collected depends on the service you request and the information you choose or are required to provide.
EOT,
                        ],
                        [
                            'order' => 3,
                            'visible' => true,
                            'heading' => '3. How We Use Your Information',
                            'body' => <<<'EOT'
We may use personal information to respond to questions and messages, process appointment requests, communicate with you about services you have requested, support clinic operations, and provide healthcare services.

We aim to use information only for appropriate purposes connected with the relevant request, service, or applicable obligation.

We do not promise that information is used exclusively for these purposes until all actual system integrations and clinic procedures have been verified.
EOT,
                        ],
                        [
                            'order' => 4,
                            'visible' => true,
                            'heading' => '4. Medical and Health Information',
                            'body' => <<<'EOT'
Medical and health information is sensitive and should be handled with appropriate confidentiality and care.

Access to medical information should be limited to authorized personnel who need it for legitimate healthcare or clinic-related responsibilities, subject to applicable requirements.

Please avoid including unnecessary medical details in general website inquiry forms. For information about your medical records, contact the clinic through its official contact channels.
EOT,
                        ],
                        [
                            'order' => 5,
                            'visible' => true,
                            'heading' => '5. Cookies and Similar Technologies',
                            'body' => <<<'EOT'
Websites may use cookies or similar technologies to support functionality, maintain sessions, remember preferences, or understand website usage.

The specific cookies and tracking technologies used by RoyalCare Clinic depend on the website's actual configuration. Information about analytics, optional tracking, and cookie preferences should be added after the technologies used by the website have been verified.
EOT,
                        ],
                        [
                            'order' => 6,
                            'visible' => true,
                            'heading' => '6. Data Sharing and Third-Party Services',
                            'body' => <<<'EOT'
RoyalCare Clinic may rely on service providers to support website hosting, data storage, communications, image delivery, or other clinic-related operations, where applicable.

Personal information may be disclosed when required by applicable law or through other lawful circumstances.

The actual providers, information shared, and purposes of processing should be confirmed against the clinic's systems and service agreements before this policy is published.
EOT,
                        ],
                        [
                            'order' => 7,
                            'visible' => true,
                            'heading' => '7. Data Storage and International Transfers',
                            'body' => <<<'EOT'
Personal information may be stored using the clinic's configured systems and service providers. The location where information is stored or processed depends on the infrastructure and providers actually used.

Where information is transferred across borders, the clinic should assess the applicable legal requirements and relevant safeguards.

Details about hosting locations and international transfers should be confirmed before making specific promises.
EOT,
                        ],
                        [
                            'order' => 8,
                            'visible' => true,
                            'heading' => '8. Data Security',
                            'body' => <<<'EOT'
RoyalCare Clinic aims to use reasonable technical and organizational measures to protect personal information against unauthorized access, disclosure, alteration, or loss.

No electronic storage or transmission method can be guaranteed to be completely secure. Security measures and access controls should be maintained and reviewed as appropriate.

If you have concerns about the security of your personal information, please contact the clinic through its official contact channels.
EOT,
                        ],
                        [
                            'order' => 9,
                            'visible' => true,
                            'heading' => '9. Data Retention',
                            'body' => <<<'EOT'
Personal information should be retained only for as long as reasonably necessary for the purpose for which it was collected, clinic operations, applicable legal obligations, and legitimate medical recordkeeping needs.

Retention periods may differ depending on the type of information and the service provided. Information should be securely deleted or anonymized when it is no longer required, where appropriate and legally permitted.
EOT,
                        ],
                        [
                            'order' => 10,
                            'visible' => true,
                            'heading' => '10. Your Privacy Rights and Requests',
                            'body' => <<<'EOT'
You may contact RoyalCare Clinic to ask questions about your personal information or request access, correction, or deletion where applicable.

Requests may require identity verification and will be reviewed in accordance with applicable law, medical recordkeeping obligations, and other relevant requirements. Some information may need to be retained when legally required or necessary for legitimate clinical recordkeeping.

To submit a request, use the Contact Us page or the clinic's verified official contact details.
EOT,
                        ],
                        [
                            'order' => 11,
                            'visible' => true,
                            'heading' => '11. Children and Minors',
                            'body' => <<<'EOT'
When personal information concerns a child or minor, the clinic should handle it with appropriate care and consider applicable requirements concerning parental or legal guardian involvement.

Parents, guardians, or authorized representatives may contact the clinic for questions about information relating to a minor, subject to identity verification and applicable requirements.
EOT,
                        ],
                        [
                            'order' => 12,
                            'visible' => true,
                            'heading' => '12. External Links',
                            'body' => <<<'EOT'
Our website may contain links to external websites or services. Those websites may have their own privacy policies and practices.

RoyalCare Clinic does not control the privacy practices of external websites. We encourage visitors to review the applicable privacy information when using third-party websites.
EOT,
                        ],
                        [
                            'order' => 13,
                            'visible' => true,
                            'heading' => '13. Changes to This Privacy Policy',
                            'body' => <<<'EOT'
This Privacy Policy may be updated when the clinic's practices, systems, services, or applicable requirements change.

The latest published version will be available on this page. Significant changes should be communicated through appropriate channels when necessary.

Please review this page periodically to stay informed about the clinic's privacy practices.
EOT,
                        ],
                        [
                            'order' => 14,
                            'visible' => true,
                            'heading' => '14. Contact Us About Privacy',
                            'body' => <<<'EOT'
If you have questions, concerns, or requests relating to this Privacy Policy or your personal information, please contact RoyalCare Clinic through our Contact Us page or our verified official contact channels.

Please do not send passwords, payment credentials, or unnecessary sensitive medical information through general website messages.
EOT,
                        ],
                    ],
                ],
                'km' => [
                    'title' => 'គោលការណ៍ឯកជនភាព | Privacy Policy',
                    'introduction' => 'RoyalCare Clinic គោរពសិទ្ធិឯកជនរបស់អ្នក និងប្តេជ្ញាចាត់ចែងព័ត៌មានផ្ទាល់ខ្លួនដោយការទទួលខុសត្រូវ។ គោលការណ៍នេះពន្យល់អំពីការប្រមូល ការប្រើប្រាស់ ការរក្សាទុក និងការការពារព័ត៌មាន នៅពេលអ្នកចូលមើលគេហទំព័រ ឬប្រើប្រាស់សេវាកម្មរបស់គ្លីនិក។',
                    'sections' => [
                        [
                            'order' => 1,
                            'visible' => true,
                            'heading' => '១. អំពី RoyalCare Clinic',
                            'body' => <<<'EOT'
RoyalCare Clinic ផ្តល់សេវាថែទាំសុខភាព និងដំណើរការគេហទំព័រសាធារណៈសម្រាប់ចែករំលែកព័ត៌មានអំពីគ្លីនិក សេវាកម្ម និងចំណេះដឹងទាក់ទងនឹងសុខភាព។

RoyalCare Clinic ទទួលខុសត្រូវលើការគ្រប់គ្រងព័ត៌មានផ្ទាល់ខ្លួនដែលត្រូវបានចាត់ចែងតាមរយៈគេហទំព័រ និងប្រតិបត្តិការរបស់គ្លីនិក ស្របតាមតម្រូវការដែលអនុវត្ត។

ប្រសិនបើអ្នកមានសំណួរអំពីគោលការណ៍នេះ សូមទាក់ទងគ្លីនិកតាមរយៈទំព័រ Contact Us ឬមធ្យោបាយទំនាក់ទំនងផ្លូវការដែលបានផ្ទៀងផ្ទាត់។
EOT,
                        ],
                        [
                            'order' => 2,
                            'visible' => true,
                            'heading' => '២. ព័ត៌មានដែលយើងប្រមូល',
                            'body' => <<<'EOT'
អាស្រ័យលើរបៀបដែលអ្នកប្រើប្រាស់សេវាកម្មរបស់ RoyalCare Clinic យើងអាចប្រមូលព័ត៌មានផ្ទាល់ខ្លួន ដូចជា ឈ្មោះ អាសយដ្ឋានអ៊ីមែល លេខទូរស័ព្ទ ព័ត៌មានស្នើសុំការណាត់ជួប និងសារដែលអ្នកផ្ញើតាមរយៈគេហទំព័រ។

នៅពេលអ្នកទទួលសេវាថែទាំសុខភាព ព័ត៌មានវេជ្ជសាស្ត្រដែលពាក់ព័ន្ធអាចត្រូវបានចាត់ចែងជាផ្នែកមួយនៃការថែទាំរបស់អ្នក។ ព័ត៌មានទាំងនេះអាចរួមមាន ប្រវត្តិជំងឺ ការធ្វើរោគវិនិច្ឆ័យ វេជ្ជបញ្ជា លទ្ធផលតេស្ត និងកំណត់ត្រាព្យាបាល តាមករណីជាក់ស្តែង។

ប្រភេទព័ត៌មានដែលត្រូវបានប្រមូល អាស្រ័យលើសេវាកម្មដែលអ្នកស្នើសុំ និងព័ត៌មានដែលអ្នកផ្តល់ ឬត្រូវផ្តល់តាមតម្រូវការ។
EOT,
                        ],
                        [
                            'order' => 3,
                            'visible' => true,
                            'heading' => '៣. របៀបដែលយើងប្រើប្រាស់ព័ត៌មាន',
                            'body' => <<<'EOT'
យើងអាចប្រើប្រាស់ព័ត៌មានផ្ទាល់ខ្លួន ដើម្បីឆ្លើយតបសំណួរ និងសារ ដំណើរការសំណើសុំការណាត់ជួប ទាក់ទងអ្នកអំពីសេវាកម្មដែលអ្នកបានស្នើសុំ គាំទ្រប្រតិបត្តិការរបស់គ្លីនិក និងផ្តល់សេវាថែទាំសុខភាព។

យើងមានគោលបំណងប្រើប្រាស់ព័ត៌មានសម្រាប់គោលបំណងសមស្របដែលពាក់ព័ន្ធនឹងសំណើ សេវាកម្ម ឬកាតព្វកិច្ចដែលអនុវត្ត។
EOT,
                        ],
                        [
                            'order' => 4,
                            'visible' => true,
                            'heading' => '៤. ព័ត៌មានវេជ្ជសាស្ត្រ និងសុខភាព',
                            'body' => <<<'EOT'
ព័ត៌មានវេជ្ជសាស្ត្រ និងសុខភាពជាព័ត៌មានរសើប ដែលគួរត្រូវបានចាត់ចែងដោយការសម្ងាត់ និងការប្រុងប្រយ័ត្នសមស្រប។

ការចូលប្រើព័ត៌មានវេជ្ជសាស្ត្រគួរត្រូវបានកំណត់ចំពោះបុគ្គលិកដែលមានសិទ្ធិ និងត្រូវការព័ត៌មាននោះសម្រាប់ការថែទាំអ្នកជំងឺ ឬការងាររបស់គ្លីនិក ស្របតាមតម្រូវការដែលអនុវត្ត។

សូមជៀសវាងការបញ្ចូលព័ត៌មានវេជ្ជសាស្ត្រដែលមិនចាំបាច់ក្នុងទម្រង់សំណួរទូទៅលើគេហទំព័រ។ ប្រសិនបើអ្នកចង់សាកសួរអំពីកំណត់ត្រាវេជ្ជសាស្ត្ររបស់អ្នក សូមទាក់ទងគ្លីនិកតាមមធ្យោបាយផ្លូវការ។
EOT,
                        ],
                        [
                            'order' => 5,
                            'visible' => true,
                            'heading' => '៥. Cookies និងបច្ចេកវិទ្យាស្រដៀងគ្នា',
                            'body' => <<<'EOT'
គេហទំព័រអាចប្រើ Cookies ឬបច្ចេកវិទ្យាស្រដៀងគ្នា ដើម្បីគាំទ្រមុខងារគេហទំព័រ រក្សាសម័យប្រើប្រាស់ ចងចាំចំណូលចិត្ត ឬយល់ដឹងអំពីការប្រើប្រាស់គេហទំព័រ។

Cookies និងបច្ចេកវិទ្យាតាមដានដែល RoyalCare Clinic ប្រើប្រាស់ជាក់ស្តែង អាស្រ័យលើការកំណត់របស់គេហទំព័រ។ ព័ត៌មានអំពី Analytics, ការតាមដានបន្ថែម និងជម្រើស Cookies គួរត្រូវបានបន្ថែមបន្ទាប់ពីបានផ្ទៀងផ្ទាត់បច្ចេកវិទ្យាដែលគេហទំព័រកំពុងប្រើប្រាស់។
EOT,
                        ],
                        [
                            'order' => 6,
                            'visible' => true,
                            'heading' => '៦. ការចែករំលែកព័ត៌មាន និងសេវាកម្មភាគីទីបី',
                            'body' => <<<'EOT'
RoyalCare Clinic អាចប្រើប្រាស់អ្នកផ្តល់សេវាផ្សេងៗ ដើម្បីគាំទ្រការបង្ហោះគេហទំព័រ ការរក្សាទុកទិន្នន័យ ការទំនាក់ទំនង ការបង្ហាញរូបភាព ឬប្រតិបត្តិការផ្សេងៗរបស់គ្លីនិក តាមករណីជាក់ស្តែង។

ព័ត៌មានផ្ទាល់ខ្លួនអាចត្រូវបានបង្ហាញនៅពេលច្បាប់ដែលអនុវត្តតម្រូវ ឬក្នុងកាលៈទេសៈផ្សេងទៀតដែលអនុញ្ញាតដោយច្បាប់។

អ្នកផ្តល់សេវាជាក់លាក់ ប្រភេទព័ត៌មានដែលត្រូវបានចែករំលែក និងគោលបំណងនៃការចាត់ចែង ត្រូវផ្ទៀងផ្ទាត់ជាមួយប្រព័ន្ធ និងកិច្ចព្រមព្រៀងសេវាកម្មរបស់គ្លីនិក មុនពេលបោះពុម្ពគោលការណ៍នេះ។
EOT,
                        ],
                        [
                            'order' => 7,
                            'visible' => true,
                            'heading' => '៧. ការរក្សាទុកទិន្នន័យ និងការផ្ទេរទៅក្រៅប្រទេស',
                            'body' => <<<'EOT'
ព័ត៌មានផ្ទាល់ខ្លួនអាចត្រូវបានរក្សាទុកក្នុងប្រព័ន្ធ និងតាមរយៈអ្នកផ្តល់សេវាដែលគ្លីនិកបានកំណត់រចនាសម្ព័ន្ធ។ ទីតាំងនៃការរក្សាទុក ឬចាត់ចែងព័ត៌មាន អាស្រ័យលើហេដ្ឋារចនាសម្ព័ន្ធ និងអ្នកផ្តល់សេវាដែលកំពុងប្រើប្រាស់ជាក់ស្តែង។

ប្រសិនបើមានការផ្ទេរព័ត៌មានឆ្លងកាត់ព្រំដែន គ្លីនិកគួរត្រូវវាយតម្លៃតម្រូវការច្បាប់ដែលអនុវត្ត និងវិធានការការពារពាក់ព័ន្ធ។
EOT,
                        ],
                        [
                            'order' => 8,
                            'visible' => true,
                            'heading' => '៨. សុវត្ថិភាពទិន្នន័យ',
                            'body' => <<<'EOT'
RoyalCare Clinic មានគោលបំណងប្រើប្រាស់វិធានការបច្ចេកទេស និងវិធានការគ្រប់គ្រងសមស្រប ដើម្បីការពារព័ត៌មានផ្ទាល់ខ្លួនពីការចូលប្រើ ការបង្ហាញ ការកែប្រែ ឬការបាត់បង់ដោយគ្មានការអនុញ្ញាត។

ទោះជាយ៉ាងណា ការរក្សាទុក ឬបញ្ជូនព័ត៌មានតាមប្រព័ន្ធអេឡិចត្រូនិកមិនអាចធានាសុវត្ថិភាពបានទាំងស្រុងនោះទេ។ វិធានការសុវត្ថិភាព និងការគ្រប់គ្រងសិទ្ធិចូលប្រើគួរត្រូវបានថែរក្សា និងពិនិត្យតាមភាពសមស្រប។
EOT,
                        ],
                        [
                            'order' => 9,
                            'visible' => true,
                            'heading' => '៩. រយៈពេលរក្សាទុកព័ត៌មាន',
                            'body' => <<<'EOT'
ព័ត៌មានផ្ទាល់ខ្លួនគួរត្រូវបានរក្សាទុកត្រឹមរយៈពេលដែលចាំបាច់សម្រាប់គោលបំណងនៃការប្រមូល ប្រតិបត្តិការរបស់គ្លីនិក កាតព្វកិច្ចផ្លូវច្បាប់ និងតម្រូវការរក្សាទុកកំណត់ត្រាវេជ្ជសាស្ត្រដែលសមស្រប។

រយៈពេលរក្សាទុកអាចខុសគ្នាទៅតាមប្រភេទព័ត៌មាន និងសេវាកម្មដែលបានផ្តល់។ នៅពេលព័ត៌មានលែងចាំបាច់ គួរត្រូវបានលុប ឬធ្វើឱ្យមិនអាចកំណត់អត្តសញ្ញាណបាន ប្រសិនបើសមស្រប និងច្បាប់អនុញ្ញាត។
EOT,
                        ],
                        [
                            'order' => 10,
                            'visible' => true,
                            'heading' => '១០. សិទ្ធិ និងសំណើទាក់ទងនឹងឯកជនភាព',
                            'body' => <<<'EOT'
អ្នកអាចទាក់ទង RoyalCare Clinic ដើម្បីសាកសួរអំពីព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នក ឬស្នើសុំចូលមើល កែតម្រូវ ឬលុបព័ត៌មាន នៅពេលសិទ្ធិទាំងនេះអាចអនុវត្តបាន។

សំណើអាចតម្រូវឱ្យមានការផ្ទៀងផ្ទាត់អត្តសញ្ញាណ ហើយនឹងត្រូវពិនិត្យដោយគិតគូរពីច្បាប់ដែលអនុវត្ត កាតព្វកិច្ចរក្សាទុកកំណត់ត្រាវេជ្ជសាស្ត្រ និងតម្រូវការពាក់ព័ន្ធផ្សេងៗ។ ព័ត៌មានមួយចំនួនអាចត្រូវរក្សាទុក ប្រសិនបើច្បាប់តម្រូវ ឬចាំបាច់សម្រាប់កំណត់ត្រាព្យាបាល។

អ្នកអាចដាក់សំណើតាមរយៈទំព័រ Contact Us ឬព័ត៌មានទំនាក់ទំនងផ្លូវការដែលបានផ្ទៀងផ្ទាត់។
EOT,
                        ],
                        [
                            'order' => 11,
                            'visible' => true,
                            'heading' => '១១. កុមារ និងអនីតិជន',
                            'body' => <<<'EOT'
នៅពេលព័ត៌មានផ្ទាល់ខ្លួនពាក់ព័ន្ធនឹងកុមារ ឬអនីតិជន គ្លីនិកគួរចាត់ចែងព័ត៌មាននោះដោយការប្រុងប្រយ័ត្នសមស្រប និងគិតគូរពីតម្រូវការដែលទាក់ទងនឹងឪពុកម្តាយ ឬអាណាព្យាបាលស្របច្បាប់។

ឪពុកម្តាយ អាណាព្យាបាល ឬអ្នកតំណាងដែលមានសិទ្ធិ អាចទាក់ទងគ្លីនិកដើម្បីសាកសួរអំពីព័ត៌មានរបស់អនីតិជន ដោយអនុលោមតាមការផ្ទៀងផ្ទាត់អត្តសញ្ញាណ និងតម្រូវការដែលអនុវត្ត។
EOT,
                        ],
                        [
                            'order' => 12,
                            'visible' => true,
                            'heading' => '១២. តំណភ្ជាប់ទៅគេហទំព័រខាងក្រៅ',
                            'body' => <<<'EOT'
គេហទំព័ររបស់យើងអាចមានតំណភ្ជាប់ទៅកាន់គេហទំព័រ ឬសេវាកម្មខាងក្រៅ។ គេហទំព័រទាំងនោះអាចមានគោលការណ៍ឯកជនភាព និងការអនុវត្តផ្ទាល់ខ្លួន។

RoyalCare Clinic មិនគ្រប់គ្រងការអនុវត្តឯកជនភាពរបស់គេហទំព័រខាងក្រៅទាំងនោះទេ។ យើងណែនាំឱ្យអ្នកអានគោលការណ៍ឯកជនភាពរបស់គេហទំព័រភាគីទីបី មុនពេលប្រើប្រាស់។
EOT,
                        ],
                        [
                            'order' => 13,
                            'visible' => true,
                            'heading' => '១៣. ការផ្លាស់ប្តូរគោលការណ៍នេះ',
                            'body' => <<<'EOT'
យើងអាចធ្វើបច្ចុប្បន្នភាពគោលការណ៍ឯកជនភាពនេះ នៅពេលការអនុវត្ត ប្រព័ន្ធ សេវាកម្ម ឬតម្រូវការដែលអនុវត្តមានការផ្លាស់ប្តូរ។

កំណែចុងក្រោយដែលបានបោះពុម្ពនឹងមាននៅលើទំព័រនេះ។ ការផ្លាស់ប្តូរសំខាន់ៗគួរត្រូវបានជូនដំណឹងតាមមធ្យោបាយសមស្រប នៅពេលចាំបាច់។
EOT,
                        ],
                        [
                            'order' => 14,
                            'visible' => true,
                            'heading' => '១៤. ទាក់ទងយើងអំពីឯកជនភាព',
                            'body' => <<<'EOT'
ប្រសិនបើអ្នកមានសំណួរ កង្វល់ ឬសំណើទាក់ទងនឹងគោលការណ៍នេះ ឬព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នក សូមទាក់ទង RoyalCare Clinic តាមរយៈទំព័រ Contact Us ឬមធ្យោបាយទំនាក់ទំនងផ្លូវការដែលបានផ្ទៀងផ្ទាត់។

សូមកុំផ្ញើពាក្យសម្ងាត់ ព័ត៌មានសម្ងាត់នៃការទូទាត់ ឬព័ត៌មានវេជ្ជសាស្ត្ររសើបដែលមិនចាំបាច់តាមរយៈសារទូទៅលើគេហទំព័រ។
EOT,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function termsOfUse(): array
    {
        return [
            'slug' => 'terms-of-use',
            'title' => 'Terms of Use',
            'title_km' => 'លក្ខខណ្ឌនៃការប្រើប្រាស់ | Terms of Use',
            'excerpt' => 'The terms governing the use of this website.',
            'excerpt_km' => 'លក្ខខណ្ឌដែលគ្រប់គ្រងការប្រើប្រាស់គេហទំព័រនេះ។',
            'internal_notes' => <<<'EOT'
Before publishing, review these Terms of Use against RoyalCare Clinic's actual website functionality, appointment workflow, intellectual property ownership, third-party links, and applicable Cambodian law. Verify that the medical information disclaimer is appropriate for the clinic's services. Confirm that all liability limitations and user obligations are legally appropriate. Do not publish until the content has been reviewed and approved.
EOT,
            'policy_content' => [
                'en' => [
                    'title' => 'Terms of Use',
                    'introduction' => 'Welcome to the RoyalCare Clinic website. These Terms of Use explain the conditions for accessing and using our website. By using this website, you agree to use it responsibly and in accordance with these terms and applicable laws.',
                    'sections' => [
                        [
                            'order' => 1,
                            'visible' => true,
                            'heading' => '1. About This Website',
                            'body' => <<<'EOT'
The RoyalCare Clinic website provides information about our clinic, healthcare services, health-related resources, and ways to contact us. Website content is intended to help visitors understand our services and communicate with the clinic.

These terms apply to your use of this public website. Separate terms, consent forms, or agreements may apply to specific healthcare services where appropriate.
EOT,
                        ],
                        [
                            'order' => 2,
                            'visible' => true,
                            'heading' => '2. Acceptable Use',
                            'body' => <<<'EOT'
You agree to use this website lawfully and responsibly. You must not attempt to disrupt the website, interfere with its security, gain unauthorized access to systems or information, introduce malicious software, or misuse website forms and communication channels.

You must not submit information that is knowingly false, unlawful, threatening, or intended to harm others.
EOT,
                        ],
                        [
                            'order' => 3,
                            'visible' => true,
                            'heading' => '3. Healthcare Information Disclaimer',
                            'body' => <<<'EOT'
Information provided on this website is for general informational and educational purposes only. It is not a substitute for professional medical advice, diagnosis, or treatment.

Do not disregard professional medical advice or delay seeking care because of information found on this website. For personal medical concerns, consult a qualified healthcare professional.

If you believe you are experiencing a medical emergency, seek emergency medical assistance immediately.
EOT,
                        ],
                        [
                            'order' => 4,
                            'visible' => true,
                            'heading' => '4. Appointments and Inquiries',
                            'body' => <<<'EOT'
Website appointment requests and contact messages are requests for communication or scheduling. A submitted request does not necessarily mean that an appointment has been confirmed.

The clinic may need to contact you to verify details, confirm availability, or provide further instructions. Please use the clinic's verified contact channels for questions about your appointment.

Do not include passwords, payment credentials, or unnecessary sensitive medical information in general website forms.
EOT,
                        ],
                        [
                            'order' => 5,
                            'visible' => true,
                            'heading' => '5. User Information and Privacy',
                            'body' => <<<'EOT'
Your use of this website may involve the processing of personal information. Please review our Privacy Policy to understand how information may be collected, used, stored, and protected.

You are responsible for ensuring that information you submit is accurate to the best of your knowledge and that you are authorized to provide it.
EOT,
                        ],
                        [
                            'order' => 6,
                            'visible' => true,
                            'heading' => '6. Intellectual Property',
                            'body' => <<<'EOT'
Website content, including text, branding, logos, graphics, photographs, and other materials, may be protected by intellectual property laws or belong to their respective owners.

Unless permitted by law or authorized by the relevant rights holder, you must not reproduce, modify, distribute, or commercially exploit protected content without permission.
EOT,
                        ],
                        [
                            'order' => 7,
                            'visible' => true,
                            'heading' => '7. Third-Party Links and Services',
                            'body' => <<<'EOT'
This website may contain links to third-party websites or services for convenience or informational purposes. These external services may be governed by their own terms and privacy policies.

RoyalCare Clinic does not control third-party websites and is not responsible for their content, availability, or practices. You should review their terms before using them.
EOT,
                        ],
                        [
                            'order' => 8,
                            'visible' => true,
                            'heading' => '8. Website Availability and Changes',
                            'body' => <<<'EOT'
We aim to keep the website available and its information reasonably up to date. However, we do not guarantee uninterrupted availability, error-free operation, or that every item of information will always be complete or current.

We may update, suspend, or discontinue parts of the website when reasonably necessary, subject to applicable requirements.
EOT,
                        ],
                        [
                            'order' => 9,
                            'visible' => true,
                            'heading' => '9. Disclaimer and Limitation of Liability',
                            'body' => <<<'EOT'
To the extent permitted by applicable law, the website and its general informational content are provided without a guarantee that they will always be available, accurate, complete, or suitable for every purpose.

Nothing in these terms excludes or limits any responsibility that cannot lawfully be excluded or limited, including applicable duties relating to healthcare services or consumer protection.
EOT,
                        ],
                        [
                            'order' => 10,
                            'visible' => true,
                            'heading' => '10. Changes to These Terms',
                            'body' => <<<'EOT'
We may update these Terms of Use when our website, services, or applicable requirements change. The updated version will be published on this page, together with an appropriate update date.

Your continued use of the website after changes take effect may be subject to the updated terms, to the extent permitted by applicable law.
EOT,
                        ],
                        [
                            'order' => 11,
                            'visible' => true,
                            'heading' => '11. Applicable Law',
                            'body' => <<<'EOT'
These Terms of Use should be interpreted in accordance with applicable laws and regulations of the Kingdom of Cambodia, subject to any mandatory legal requirements that apply to the relevant circumstances.

Any specific legal rights, remedies, or dispute procedures will be governed by the applicable law.
EOT,
                        ],
                        [
                            'order' => 12,
                            'visible' => true,
                            'heading' => '12. Contact Us',
                            'body' => <<<'EOT'
If you have questions about these Terms of Use or the RoyalCare Clinic website, please contact us through our Contact Us page or the clinic's verified official contact channels.

Please use the appropriate official channels for appointment questions, service inquiries, or privacy-related requests.
EOT,
                        ],
                    ],
                ],
                'km' => [
                    'title' => 'លក្ខខណ្ឌនៃការប្រើប្រាស់ | Terms of Use',
                    'introduction' => 'សូមស្វាគមន៍មកកាន់គេហទំព័រ RoyalCare Clinic។ លក្ខខណ្ឌនៃការប្រើប្រាស់នេះពន្យល់អំពីលក្ខខណ្ឌនានាសម្រាប់ការចូលប្រើប្រាស់ និងការប្រើប្រាស់គេហទំព័ររបស់យើង។ នៅពេលប្រើប្រាស់គេហទំព័រនេះ អ្នកយល់ព្រមប្រើប្រាស់វាដោយការទទួលខុសត្រូវ និងអនុលោមតាមលក្ខខណ្ឌទាំងនេះ ព្រមទាំងច្បាប់ដែលអនុវត្ត។',
                    'sections' => [
                        [
                            'order' => 1,
                            'visible' => true,
                            'heading' => '១. អំពីគេហទំព័រនេះ',
                            'body' => <<<'EOT'
គេហទំព័រ RoyalCare Clinic ផ្តល់ព័ត៌មានអំពីគ្លីនិក សេវាថែទាំសុខភាព ធនធានចំណេះដឹងទាក់ទងនឹងសុខភាព និងមធ្យោបាយទំនាក់ទំនងជាមួយគ្លីនិក។ ខ្លឹមសារនៅលើគេហទំព័រមានគោលបំណងជួយអ្នកទស្សនាស្វែងយល់អំពីសេវាកម្ម និងទាក់ទងគ្លីនិក។

លក្ខខណ្ឌទាំងនេះអនុវត្តចំពោះការប្រើប្រាស់គេហទំព័រសាធារណៈនេះ។ លក្ខខណ្ឌដាច់ដោយឡែក ឯកសារយល់ព្រម ឬកិច្ចព្រមព្រៀងផ្សេងៗអាចអនុវត្តចំពោះសេវាថែទាំសុខភាពជាក់លាក់ តាមភាពសមស្រប។
EOT,
                        ],
                        [
                            'order' => 2,
                            'visible' => true,
                            'heading' => '២. ការប្រើប្រាស់ដែលអាចទទួលយកបាន',
                            'body' => <<<'EOT'
អ្នកយល់ព្រមប្រើប្រាស់គេហទំព័រនេះដោយស្របច្បាប់ និងមានការទទួលខុសត្រូវ។ អ្នកមិនត្រូវព្យាយាមរំខានដល់ដំណើរការគេហទំព័រ ប៉ះពាល់ដល់សុវត្ថិភាព ព្យាយាមចូលប្រើប្រព័ន្ធ ឬព័ត៌មានដោយគ្មានការអនុញ្ញាត បញ្ចូលកម្មវិធីបង្កគ្រោះថ្នាក់ ឬប្រើប្រាស់ទម្រង់ និងមធ្យោបាយទំនាក់ទំនងខុសគោលបំណងឡើយ។

អ្នកមិនត្រូវដាក់ស្នើព័ត៌មានដែលអ្នកដឹងថាមិនពិត ខុសច្បាប់ គំរាមកំហែង ឬមានបំណងបង្កគ្រោះថ្នាក់ដល់អ្នកដទៃឡើយ។
EOT,
                        ],
                        [
                            'order' => 3,
                            'visible' => true,
                            'heading' => '៣. ការបញ្ជាក់អំពីព័ត៌មានសុខភាព',
                            'body' => <<<'EOT'
ព័ត៌មានដែលផ្តល់ជូននៅលើគេហទំព័រនេះមានគោលបំណងសម្រាប់ចំណេះដឹង និងការអប់រំទូទៅប៉ុណ្ណោះ។ ព័ត៌មានទាំងនេះមិនមែនជាការជំនួសឱ្យការប្រឹក្សា ការធ្វើរោគវិនិច្ឆ័យ ឬការព្យាបាលពីអ្នកជំនាញវេជ្ជសាស្ត្រឡើយ។

សូមកុំមើលរំលងការណែនាំពីអ្នកជំនាញសុខភាព ឬពន្យារពេលស្វែងរកការថែទាំ ដោយសារព័ត៌មានដែលអ្នកបានអាននៅលើគេហទំព័រនេះ។ ប្រសិនបើអ្នកមានបញ្ហាសុខភាពផ្ទាល់ខ្លួន សូមពិគ្រោះជាមួយអ្នកជំនាញសុខភាពដែលមានសមត្ថភាព។

ប្រសិនបើអ្នកគិតថាអ្នកកំពុងជួបប្រទះភាពអាសន្នផ្នែកវេជ្ជសាស្ត្រ សូមស្វែងរកជំនួយសង្គ្រោះបន្ទាន់ភ្លាមៗ។
EOT,
                        ],
                        [
                            'order' => 4,
                            'visible' => true,
                            'heading' => '៤. ការណាត់ជួប និងការសាកសួរ',
                            'body' => <<<'EOT'
សំណើសុំការណាត់ជួប និងសារទំនាក់ទំនងតាមរយៈគេហទំព័រ គឺជាសំណើសម្រាប់ការទំនាក់ទំនង ឬការរៀបចំពេលវេលា។ ការដាក់ស្នើសំណើមិនមានន័យថាការណាត់ជួបត្រូវបានបញ្ជាក់ជាផ្លូវការរួចហើយនោះទេ។

គ្លីនិកអាចត្រូវទាក់ទងអ្នក ដើម្បីផ្ទៀងផ្ទាត់ព័ត៌មាន ពិនិត្យភាពទំនេរ ឬផ្តល់ការណែនាំបន្ថែម។ សម្រាប់សំណួរអំពីការណាត់ជួប សូមប្រើមធ្យោបាយទំនាក់ទំនងផ្លូវការដែលបានផ្ទៀងផ្ទាត់របស់គ្លីនិក។

សូមកុំបញ្ចូលពាក្យសម្ងាត់ ព័ត៌មានសម្ងាត់សម្រាប់ការទូទាត់ ឬព័ត៌មានវេជ្ជសាស្ត្ររសើបដែលមិនចាំបាច់ក្នុងទម្រង់ទូទៅលើគេហទំព័រ។
EOT,
                        ],
                        [
                            'order' => 5,
                            'visible' => true,
                            'heading' => '៥. ព័ត៌មានអ្នកប្រើប្រាស់ និងឯកជនភាព',
                            'body' => <<<'EOT'
ការប្រើប្រាស់គេហទំព័រនេះអាចពាក់ព័ន្ធនឹងការចាត់ចែងព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នក។ សូមអានគោលការណ៍ឯកជនភាពរបស់យើង ដើម្បីស្វែងយល់អំពីរបៀបដែលព័ត៌មានអាចត្រូវបានប្រមូល ប្រើប្រាស់ រក្សាទុក និងការពារ។

អ្នកទទួលខុសត្រូវក្នុងការធានាថាព័ត៌មានដែលអ្នកដាក់ស្នើមានភាពត្រឹមត្រូវតាមចំណេះដឹងរបស់អ្នក និងអ្នកមានសិទ្ធិផ្តល់ព័ត៌មាននោះ។
EOT,
                        ],
                        [
                            'order' => 6,
                            'visible' => true,
                            'heading' => '៦. កម្មសិទ្ធិបញ្ញា',
                            'body' => <<<'EOT'
ខ្លឹមសារនៅលើគេហទំព័រ រួមមានអត្ថបទ ឈ្មោះម៉ាក និមិត្តសញ្ញា ក្រាហ្វិក រូបថត និងសម្ភារៈផ្សេងៗ អាចត្រូវបានការពារដោយច្បាប់កម្មសិទ្ធិបញ្ញា ឬជាកម្មសិទ្ធិរបស់ម្ចាស់សិទ្ធិរៀងៗខ្លួន។

លើកលែងតែច្បាប់អនុញ្ញាត ឬម្ចាស់សិទ្ធិពាក់ព័ន្ធបានផ្តល់ការអនុញ្ញាត អ្នកមិនត្រូវចម្លង កែប្រែ ចែកចាយ ឬប្រើប្រាស់ខ្លឹមសារដែលត្រូវបានការពារដើម្បីផលប្រយោជន៍ពាណិជ្ជកម្មដោយគ្មានការអនុញ្ញាតឡើយ។
EOT,
                        ],
                        [
                            'order' => 7,
                            'visible' => true,
                            'heading' => '៧. តំណភ្ជាប់ និងសេវាកម្មភាគីទីបី',
                            'body' => <<<'EOT'
គេហទំព័រនេះអាចមានតំណភ្ជាប់ទៅកាន់គេហទំព័រ ឬសេវាកម្មរបស់ភាគីទីបី ដើម្បីផ្តល់ភាពងាយស្រួល ឬព័ត៌មានបន្ថែម។ សេវាកម្មខាងក្រៅទាំងនេះអាចមានលក្ខខណ្ឌប្រើប្រាស់ និងគោលការណ៍ឯកជនភាពផ្ទាល់ខ្លួន។

RoyalCare Clinic មិនគ្រប់គ្រងគេហទំព័រភាគីទីបីឡើយ ហើយមិនទទួលខុសត្រូវចំពោះខ្លឹមសារ ភាពអាចប្រើប្រាស់បាន ឬការអនុវត្តរបស់ពួកគេនោះទេ។ អ្នកគួរអានលក្ខខណ្ឌរបស់ពួកគេមុនពេលប្រើប្រាស់។
EOT,
                        ],
                        [
                            'order' => 8,
                            'visible' => true,
                            'heading' => '៨. ភាពអាចប្រើប្រាស់បាន និងការផ្លាស់ប្តូរគេហទំព័រ',
                            'body' => <<<'EOT'
យើងមានគោលបំណងរក្សាគេហទំព័រឱ្យអាចប្រើប្រាស់បាន និងធ្វើបច្ចុប្បន្នភាពព័ត៌មានតាមភាពសមស្រប។ ទោះជាយ៉ាងណា យើងមិនអាចធានាថាគេហទំព័រនឹងដំណើរការជាបន្តបន្ទាប់ ដោយគ្មានកំហុស ឬថាព័ត៌មានទាំងអស់នឹងពេញលេញ និងទាន់សម័យគ្រប់ពេលនោះទេ។

យើងអាចធ្វើបច្ចុប្បន្នភាព ផ្អាក ឬបញ្ឈប់ផ្នែកណាមួយនៃគេហទំព័រ នៅពេលមានភាពចាំបាច់សមស្រប ដោយអនុលោមតាមតម្រូវការដែលអនុវត្ត។
EOT,
                        ],
                        [
                            'order' => 9,
                            'visible' => true,
                            'heading' => '៩. ការបដិសេធការធានា និងការកំណត់ការទទួលខុសត្រូវ',
                            'body' => <<<'EOT'
ក្នុងកម្រិតដែលច្បាប់អនុញ្ញាត គេហទំព័រ និងព័ត៌មានទូទៅនៅលើគេហទំព័រត្រូវបានផ្តល់ជូនដោយគ្មានការធានាថានឹងអាចប្រើប្រាស់បានជានិច្ច មានភាពត្រឹមត្រូវ ពេញលេញ ឬសមស្របសម្រាប់គោលបំណងគ្រប់ប្រភេទនោះទេ។

គ្មានចំណុចណាមួយក្នុងលក្ខខណ្ឌទាំងនេះមានបំណងលុបចោល ឬកំណត់ការទទួលខុសត្រូវដែលច្បាប់មិនអនុញ្ញាតឱ្យលុបចោល ឬកំណត់នោះទេ រួមទាំងកាតព្វកិច្ចពាក់ព័ន្ធនឹងសេវាថែទាំសុខភាព ឬការការពារអ្នកប្រើប្រាស់ដែលអាចអនុវត្ត។
EOT,
                        ],
                        [
                            'order' => 10,
                            'visible' => true,
                            'heading' => '១០. ការផ្លាស់ប្តូរលក្ខខណ្ឌទាំងនេះ',
                            'body' => <<<'EOT'
យើងអាចធ្វើបច្ចុប្បន្នភាពលក្ខខណ្ឌនៃការប្រើប្រាស់នេះ នៅពេលគេហទំព័រ សេវាកម្ម ឬតម្រូវការដែលអនុវត្តមានការផ្លាស់ប្តូរ។ កំណែថ្មីនឹងត្រូវបានបោះពុម្ពនៅលើទំព័រនេះ ដោយបង្ហាញកាលបរិច្ឆេទធ្វើបច្ចុប្បន្នភាពសមស្រប។

ការបន្តប្រើប្រាស់គេហទំព័របន្ទាប់ពីការផ្លាស់ប្តូរចូលជាធរមាន អាចស្ថិតក្រោមលក្ខខណ្ឌដែលបានធ្វើបច្ចុប្បន្នភាព ក្នុងកម្រិតដែលច្បាប់អនុញ្ញាត។
EOT,
                        ],
                        [
                            'order' => 11,
                            'visible' => true,
                            'heading' => '១១. ច្បាប់ដែលអនុវត្ត',
                            'body' => <<<'EOT'
លក្ខខណ្ឌនៃការប្រើប្រាស់នេះគួរត្រូវបានបកស្រាយស្របតាមច្បាប់ និងបទប្បញ្ញត្តិដែលអនុវត្តនៅក្នុងព្រះរាជាណាចក្រកម្ពុជា ដោយគិតគូរពីតម្រូវការផ្លូវច្បាប់ចាំបាច់ដែលអនុវត្តចំពោះកាលៈទេសៈពាក់ព័ន្ធ។

សិទ្ធិ ដំណោះស្រាយផ្លូវច្បាប់ ឬនីតិវិធីដោះស្រាយវិវាទជាក់លាក់ណាមួយ នឹងត្រូវអនុវត្តតាមច្បាប់ដែលពាក់ព័ន្ធ។
EOT,
                        ],
                        [
                            'order' => 12,
                            'visible' => true,
                            'heading' => '១២. ទាក់ទងយើង',
                            'body' => <<<'EOT'
ប្រសិនបើអ្នកមានសំណួរអំពីលក្ខខណ្ឌនៃការប្រើប្រាស់នេះ ឬគេហទំព័រ RoyalCare Clinic សូមទាក់ទងយើងតាមរយៈទំព័រ Contact Us ឬមធ្យោបាយទំនាក់ទំនងផ្លូវការដែលបានផ្ទៀងផ្ទាត់របស់គ្លីនិក។

សូមប្រើមធ្យោបាយទំនាក់ទំនងផ្លូវការដែលសមស្រប សម្រាប់សំណួរអំពីការណាត់ជួប សេវាកម្ម ឬសំណើទាក់ទងនឹងឯកជនភាព។
EOT,
                        ],
                    ],
                ],
            ],
        ];
    }
}
