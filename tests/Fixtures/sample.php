<?php

declare(strict_types=1);

/**
 * A catalogue that exercises every rule of the formats, and the same catalogue as a .po file.
 *
 * @return array{headers: array<string, string>, messages: array<array-key, list<string>>, po: string}
 */
return [
    'headers' => [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Language' => 'fr_FR',
        'Plural-Forms' => 'nplurals=2; plural=(n > 1);',
    ],
    'messages' => [
        'Book' => ['Livre'],
        "post type\x04Book" => ['Ouvrage'],
        "%d book\0%d books" => ['%d livre', '%d livres'],
        "menu\x04File\0Files" => ['Fichier', 'Fichiers'],
        'Quote "double" and \'single\' and back\\slash and $dollar and {$brace}' => ['Guillemets "doubles", \'simples\', \\ et $dollar et {$accolade}'],
        "Tab\there\nline" => ["Tab\tici\nligne"],
        'Café ☕' => ['Café ☕ — déjà vu, 日本語'],
        '42' => ['quarante-deux'],
        'Untranslated' => [''],
        "%d cat\0%d cats" => ['%d chat', ''],
        'No forms' => [],
    ],
    'po' => <<<'PO'
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Language: fr_FR\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"

msgid "Book"
msgstr "Livre"

msgctxt "post type"
msgid "Book"
msgstr "Ouvrage"

#, c-format
msgid "%d book"
msgid_plural "%d books"
msgstr[0] "%d livre"
msgstr[1] "%d livres"

msgctxt "menu"
msgid "File"
msgid_plural "Files"
msgstr[0] "Fichier"
msgstr[1] "Fichiers"

msgid "Quote \"double\" and 'single' and back\\slash and $dollar and {$brace}"
msgstr "Guillemets \"doubles\", 'simples', \\ et $dollar et {$accolade}"

msgid "Tab\there\nline"
msgstr "Tab\tici\nligne"

msgid "Café ☕"
msgstr "Café ☕ — déjà vu, 日本語"

msgid "42"
msgstr "quarante-deux"

msgid "Untranslated"
msgstr ""
PO,
];
