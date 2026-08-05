<?php
/**
 * MK Brahman — Marathi Transliteration Engine
 * Converts Roman (English) text to Devanagari Marathi equivalents.
 * Used by smart search to find Marathi records from English queries.
 */

/**
 * Generate all possible Devanagari transliterations for a Roman input string.
 * Returns an array of candidate Devanagari strings to search.
 *
 * @param  string $input  Roman/English text (e.g. "kaustubh")
 * @return string[]       Array of Devanagari candidates
 */
function getTransliterationCandidates(string $input): array
{
    $input = mb_strtolower(trim($input), 'UTF-8');
    if ($input === '') return [];

    // Full-word lookups first (common names, cities, gotras)
    $wordMap = getWordMap();
    if (isset($wordMap[$input])) {
        // If it's an exact known word, return its mapped value + itself
        return array_unique([$wordMap[$input], $input]);
    }

    // Phoneme-based transliteration
    $devanagari = romanToDevanagari($input);

    $candidates = [$input]; // always include original for partial matches
    if ($devanagari && $devanagari !== $input) {
        $candidates[] = $devanagari;
    }

    // Also try partial word matches from the word map
    foreach ($wordMap as $rom => $dev) {
        if (str_contains($input, $rom) || str_contains($rom, $input)) {
            $candidates[] = $dev;
        }
    }

    return array_unique($candidates);
}

/**
 * Common Marathi words / proper nouns mapping.
 * Keys: lowercase Roman  →  Values: Devanagari
 */
function getWordMap(): array
{
    return [
        // ---- Names ----
        'kaustubh'    => 'कौस्तुभ',
        'siddharth'   => 'सिद्धार्थ',
        'siddhesh'    => 'सिद्धेश',
        'abhijit'     => 'अभिजित',
        'abhijeet'    => 'अभिजित',
        'amol'        => 'अमोल',
        'amruta'      => 'अमृता',
        'anand'       => 'आनंद',
        'aniket'      => 'अनिकेत',
        'anita'       => 'अनिता',
        'ankita'      => 'अंकिता',
        'ashutosh'    => 'आशुतोष',
        'atharva'     => 'अथर्व',
        'chaitanya'   => 'चैतन्य',
        'deepak'      => 'दीपक',
        'devyani'     => 'देवयानी',
        'dhruv'       => 'ध्रुव',
        'dipali'      => 'दीपाली',
        'disha'       => 'दिशा',
        'ganesh'      => 'गणेश',
        'gauri'       => 'गौरी',
        'gaurav'      => 'गौरव',
        'girish'      => 'गिरीश',
        'harshad'     => 'हर्षद',
        'hemant'      => 'हेमंत',
        'janhavi'     => 'जान्हवी',
        'jayant'      => 'जयंत',
        'kalpana'     => 'कल्पना',
        'komal'       => 'कोमल',
        'madhura'     => 'मधुरा',
        'mahesh'      => 'महेश',
        'mandar'      => 'मंदार',
        'mangesh'     => 'मंगेश',
        'mayur'       => 'मयूर',
        'milind'      => 'मिलिंद',
        'mukund'      => 'मुकुंद',
        'namrata'     => 'नम्रता',
        'neha'        => 'नेहा',
        'nikhil'      => 'निखिल',
        'nilesh'      => 'नीलेश',
        'omkar'       => 'ओंकार',
        'pallavi'     => 'पल्लवी',
        'parag'       => 'पराग',
        'pooja'       => 'पूजा',
        'puja'        => 'पूजा',
        'pradnya'     => 'प्रज्ञा',
        'priya'       => 'प्रिया',
        'priyanka'    => 'प्रियंका',
        'pushkar'     => 'पुष्कर',
        'rahul'       => 'राहुल',
        'rajesh'      => 'राजेश',
        'rakesh'      => 'राकेश',
        'rohit'       => 'रोहित',
        'rohan'       => 'रोहन',
        'rucha'       => 'रुचा',
        'rujuta'      => 'रुजुता',
        'rutuja'      => 'रुतुजा',
        'sachin'      => 'सचिन',
        'saket'       => 'साकेत',
        'sanket'      => 'संकेत',
        'santosh'     => 'संतोष',
        'sarang'      => 'सारंग',
        'savita'      => 'सविता',
        'shivani'     => 'शिवानी',
        'shreyash'    => 'श्रेयश',
        'shreya'      => 'श्रेया',
        'shubhangi'   => 'शुभांगी',
        'smita'       => 'स्मिता',
        'sneha'       => 'स्नेहा',
        'soham'       => 'सोहम',
        'suhas'       => 'सुहास',
        'sumedha'     => 'सुमेधा',
        'supriya'     => 'सुप्रिया',
        'swapnil'     => 'स्वप्निल',
        'tejashree'   => 'तेजश्री',
        'tejas'       => 'तेजस',
        'tushar'      => 'तुषार',
        'vaibhav'     => 'वैभव',
        'vaishali'    => 'वैशाली',
        'vidya'       => 'विद्या',
        'vijay'       => 'विजय',
        'vikram'      => 'विक्रम',
        'vishwas'     => 'विश्वास',
        'vivek'       => 'विवेक',
        'yogesh'      => 'योगेश',
        'yash'        => 'यश',

        // ---- Gotras ----
        'kashyap'     => 'काश्यप',
        'kasyap'      => 'काश्यप',
        'gargya'      => 'गार्ग्य',
        'bharadwaj'   => 'भारद्वाज',
        'bharadvaj'   => 'भारद्वाज',
        'vashishtha'  => 'वसिष्ठ',
        'vasishtha'   => 'वसिष्ठ',
        'vasishth'    => 'वसिष्ठ',
        'atri'        => 'अत्रि',
        'jamadagni'   => 'जमदग्नि',
        'vishwamitra' => 'विश्वामित्र',
        'vishwamithra'=> 'विश्वामित्र',
        'gautam'      => 'गौतम',
        'shandilya'   => 'शांडिल्य',
        'agastya'     => 'अगस्त्य',
        'koundilya'   => 'कौंडिण्य',
        'kaundilya'   => 'कौंडिण्य',
        'parashar'    => 'पराशर',
        'parashar'    => 'पराशर',
        'vatsa'       => 'वात्स्य',
        'laukakshi'   => 'लौकाक्षि',
        'mudgal'      => 'मुद्गल',
        'maudgalya'   => 'मौद्गल्य',

        // ---- Cities ----
        'pune'        => 'पुणे',
        'poona'       => 'पुणे',
        'mumbai'      => 'मुंबई',
        'bombay'      => 'मुंबई',
        'nagpur'      => 'नागपूर',
        'nashik'      => 'नाशिक',
        'nasik'       => 'नाशिक',
        'aurangabad'  => 'औरंगाबाद',
        'solapur'     => 'सोलापूर',
        'kolhapur'    => 'कोल्हापूर',
        'thane'       => 'ठाणे',
        'navi mumbai' => 'नवी मुंबई',
        'navimumbai'  => 'नवी मुंबई',
        'satara'      => 'सातारा',
        'sangli'      => 'सांगली',
        'akola'       => 'अकोला',
        'amravati'    => 'अमरावती',
        'latur'       => 'लातूर',
        'nanded'      => 'नांदेड',
        'jalgaon'     => 'जळगाव',
        'dhule'       => 'धुळे',
        'ratnagiri'   => 'रत्नागिरी',
        'sindhudurg'  => 'सिंधुदुर्ग',
        'alibaug'     => 'अलिबाग',
        'vasai'       => 'वसई',
        'virar'       => 'विरार',
        'dombivli'    => 'डोंबिवली',
        'kalyan'      => 'कल्याण',
        'badlapur'    => 'बदलापूर',
        'palghar'     => 'पालघर',
        'shirdi'      => 'शिर्डी',
        'pandharpur'  => 'पंढरपूर',
        'wai'         => 'वाई',
        'mahabaleshwar' => 'महाबळेश्वर',
        'lonavala'    => 'लोणावळा',
        'khandala'    => 'खंडाळा',
        'pimpri'      => 'पिंपरी',
        'chinchwad'   => 'चिंचवड',
        'pimprichinchwad' => 'पिंपरी चिंचवड',
        'baramati'    => 'बारामती',
        'hadapsar'    => 'हडपसर',
        'kothrud'     => 'कोथरूड',
        'warje'       => 'वारजे',
        'baner'       => 'बाणेर',
        'wakad'       => 'वाकड',
        'hinjewadi'   => 'हिंजवडी',
        'kharadi'     => 'खराडी',
        'viman nagar' => 'विमान नगर',

        // ---- Education keywords ----
        'engineering' => 'अभियांत्रिकी',
        'medical'     => 'वैद्यकीय',
        'commerce'    => 'वाणिज्य',
        'arts'        => 'कला',
        'law'         => 'कायदा',
        'management'  => 'व्यवस्थापन',
    ];
}

/**
 * Phoneme-based Roman → Devanagari transliteration.
 * Handles common patterns in Marathi names.
 *
 * @param  string $input  Lowercase Roman text
 * @return string         Devanagari transliteration
 */
function romanToDevanagari(string $input): string
{
    // Order matters — longer patterns first to prevent partial matches
    $map = [
        // Consonant clusters
        'shri'  => 'श्री',
        'shr'   => 'श्र',
        'str'   => 'स्त्र',
        'ksh'   => 'क्ष',
        'gny'   => 'ज्ञ',
        'dny'   => 'ज्ञ',
        'jnya'  => 'ज्ञ',
        'tra'   => 'त्र',
        'tri'   => 'त्रि',
        'tru'   => 'त्रु',
        'dra'   => 'द्र',
        'dri'   => 'द्रि',
        'pra'   => 'प्र',
        'pri'   => 'प्रि',
        'pre'   => 'प्रे',
        'bra'   => 'ब्र',
        'bri'   => 'ब्रि',
        'gra'   => 'ग्र',
        'kra'   => 'क्र',
        'swa'   => 'स्व',
        'swi'   => 'स्वि',
        'sva'   => 'स्व',
        'sna'   => 'स्न',
        'sne'   => 'स्ने',
        'sma'   => 'स्म',
        'shwa'  => 'श्व',

        // Aspirated consonants (must come before simple ones)
        'kh'    => 'ख',
        'gh'    => 'घ',
        'chh'   => 'छ',
        'ch'    => 'च',
        'jh'    => 'झ',
        'tha'   => 'थ',
        'thi'   => 'थि',
        'the'   => 'थे',
        'tho'   => 'थो',
        'thu'   => 'थु',
        'dha'   => 'ध',
        'dhi'   => 'धि',
        'dhe'   => 'धे',
        'dho'   => 'धो',
        'dhu'   => 'धु',
        'th'    => 'थ',
        'dh'    => 'ध',
        'ph'    => 'फ',
        'bh'    => 'भ',
        'mh'    => 'म्ह',
        'nh'    => 'न्ह',
        'vh'    => 'व्ह',
        'lh'    => 'ल्ह',
        'rh'    => 'र्ह',

        // Retroflex (dental ड/ट types)
        'dd'    => 'ड',
        'tt'    => 'ट',
        'nn'    => 'ण',

        // Nasals
        'ng'    => 'ं',
        'nk'    => 'ंक',
        'ank'   => 'अंक',
        'nd'    => 'ंद',
        'mb'    => 'ंब',

        // Sibilants
        'sh'    => 'श',
        'ss'    => 'ष',

        // Vowel combinations — long vowels first
        'aa'    => 'आ',
        'ee'    => 'ई',
        'ii'    => 'ई',
        'oo'    => 'ऊ',
        'uu'    => 'ऊ',
        'ai'    => 'ऐ',
        'au'    => 'औ',
        'ou'    => 'औ',
        'ow'    => 'औ',
        'ae'    => 'ए',
        'ri'    => 'रि',
        'ru'    => 'रु',
        'ry'    => 'र्य',

        // Simple consonants
        'k'     => 'क',
        'g'     => 'ग',
        'c'     => 'क',
        'j'     => 'ज',
        't'     => 'त',
        'd'     => 'द',
        'n'     => 'न',
        'p'     => 'प',
        'b'     => 'ब',
        'm'     => 'म',
        'y'     => 'य',
        'r'     => 'र',
        'l'     => 'ल',
        'v'     => 'व',
        'w'     => 'व',
        's'     => 'स',
        'h'     => 'ह',
        'f'     => 'फ',
        'z'     => 'झ',
        'x'     => 'क्ष',
        'q'     => 'क',

        // Simple vowels
        'a'     => 'अ',
        'e'     => 'ए',
        'i'     => 'इ',
        'o'     => 'ओ',
        'u'     => 'उ',
    ];

    $result = '';
    $len    = strlen($input);
    $i      = 0;

    while ($i < $len) {
        $matched = false;
        // Try longest match first (up to 4 chars)
        for ($l = min(4, $len - $i); $l >= 1; $l--) {
            $chunk = substr($input, $i, $l);
            if (isset($map[$chunk])) {
                $result  .= $map[$chunk];
                $i       += $l;
                $matched  = true;
                break;
            }
        }
        if (!$matched) {
            // Pass through unmapped characters (digits, spaces, etc.)
            $result .= $input[$i];
            $i++;
        }
    }

    return $result;
}

/**
 * Parse a numeric smart-search code:
 *   195 → gender=1, birth_year=95
 *   093 → gender=0 (Mulagi=2 mapping), birth_year=93
 *
 * Returns: ['gender' => int|null, 'birth_year' => string|null]
 *          or null if input is not a valid numeric search code.
 */
function parseNumericSearch(string $q): ?array
{
    // Must be 3 digits exactly: [gender_digit][2-digit year]
    if (!preg_match('/^([012])(\d{2})$/', $q, $m)) {
        return null;
    }

    $genderDigit = (int)$m[1];
    $birthYear   = $m[2];   // e.g. "95", "01"

    // Mapping: 1 = Mulaga (boy), 0 or 2 = Mulagi (girl)
    $gender = null;
    if ($genderDigit === 1) {
        $gender = 1;
    } elseif ($genderDigit === 0 || $genderDigit === 2) {
        $gender = 2;
    }

    return [
        'gender'     => $gender,
        'birth_year' => $birthYear,
    ];
}
