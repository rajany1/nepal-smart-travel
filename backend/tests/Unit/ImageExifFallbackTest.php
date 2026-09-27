<?php

use App\Services\Ai\ImageScreeningService;

/*
|--------------------------------------------------------------------------
| Pure-PHP EXIF fallback parser unit tests (no ext-exif required)
|--------------------------------------------------------------------------
|
| ImageScreeningService::parseExifTags() is the no-ext-exif fallback the
| pipeline uses so camera/software trace classification keeps working on
| this server (php -m: no 'exif' extension). Fixtures are byte-crafted
| TIFF/APP1/PNG-eXIf/WebP structures — no GD/Imagick, no pixel decoding.
|
| Function names are prefixed with exif… to avoid collisions with the
| other Unit test files (all Pest files load in one process).
|
*/

$GLOBALS['exif_temp_files'] = [];

function exifTempFile(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'exif');
    file_put_contents($path, $bytes);
    $GLOBALS['exif_temp_files'][] = $path;

    return $path;
}

/**
 * Build a TIFF payload carrying ASCII tags (IFD0) and an optional EXIF
 * sub-IFD (tag 0x8769), in little- or big-endian byte order.
 *
 * @param  array<int, string>  $tags  IFD0 tag => ASCII value (0x010F Make, 0x0110 Model, ...)
 * @param  array<int, string>  $exifSubIfd  EXIF sub-IFD tag => ASCII value (0x9003 DateTimeOriginal, ...)
 */
function exifCraftTiff(array $tags, bool $bigEndian = false, array $exifSubIfd = []): string
{
    $u16 = fn (int $n): string => pack($bigEndian ? 'n' : 'v', $n);
    $u32 = fn (int $n): string => pack($bigEndian ? 'N' : 'V', $n);

    $entries = [];
    foreach ($tags as $tag => $value) {
        $entries[] = ['tag' => $tag, 'value' => $value."\0"];
    }
    $hasExifIfd = $exifSubIfd !== [];
    if ($hasExifIfd) {
        $entries[] = ['tag' => 0x8769, 'long' => true];
    }

    $ifd0Size = 2 + (12 * count($entries)) + 4;
    $pos = 8 + $ifd0Size;

    foreach ($entries as &$entry) {
        if (isset($entry['value']) && strlen($entry['value']) > 4) {
            $entry['offset'] = $pos;
            $pos += strlen($entry['value']);
        }
    }
    unset($entry);

    $exifIfdOffset = null;
    $exifIfdBlob = '';
    $exifValuesBlob = '';
    if ($hasExifIfd) {
        $exifIfdOffset = $pos;
        $eValPos = $exifIfdOffset + 2 + (12 * count($exifSubIfd)) + 4;
        $eEntries = '';
        foreach ($exifSubIfd as $tag => $value) {
            $value .= "\0";
            if (strlen($value) <= 4) {
                $eEntries .= $u16($tag).$u16(2).$u32(strlen($value)).$value.str_repeat("\0", 4 - strlen($value));
            } else {
                $eEntries .= $u16($tag).$u16(2).$u32(strlen($value)).$u32($eValPos);
                $exifValuesBlob .= $value;
                $eValPos += strlen($value);
            }
        }
        $exifIfdBlob = $u16(count($exifSubIfd)).$eEntries.$u32(0);
    }

    $ifd0Blob = $u16(count($entries));
    $valuesBlob = '';
    foreach ($entries as $entry) {
        if (isset($entry['long'])) {
            $ifd0Blob .= $u16($entry['tag']).$u16(4).$u32(1).$u32($exifIfdOffset);

            continue;
        }
        $len = strlen($entry['value']);
        if ($len <= 4) {
            $ifd0Blob .= $u16($entry['tag']).$u16(2).$u32($len).$entry['value'].str_repeat("\0", 4 - $len);
        } else {
            $ifd0Blob .= $u16($entry['tag']).$u16(2).$u32($len).$u32($entry['offset']);
            $valuesBlob .= $entry['value'];
        }
    }
    $ifd0Blob .= $u32(0);

    $header = ($bigEndian ? 'MM' : 'II').$u16(42).$u32(8);

    return $header.$ifd0Blob.$valuesBlob.$exifIfdBlob.$exifValuesBlob;
}

function exifCraftJpeg(int $width, int $height, ?string $app1Payload = null): string
{
    $soi = "\xFF\xD8";
    $app0 = "\xFF\xE0".pack('n', 16)."JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

    $app1 = '';
    if ($app1Payload !== null) {
        $app1 = "\xFF\xE1".pack('n', strlen($app1Payload) + 2).$app1Payload;
    }

    $sof0 = "\xFF\xC0".pack('n', 11)."\x08".pack('nn', $height, $width)."\x01\x01\x11\x00";

    return $soi.$app0.$app1.$sof0."\xFF\xD9";
}

function exifPngChunk(string $type, string $data): string
{
    return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
}

afterEach(function () {
    foreach ($GLOBALS['exif_temp_files'] ?? [] as $path) {
        @unlink($path);
    }
    $GLOBALS['exif_temp_files'] = [];
});

test('parses Make/Model/Software/DateTime from a little-endian JPEG APP1', function () {
    $tiff = exifCraftTiff([
        0x010F => 'TestCam',                                // Make (9 bytes -> offset)
        0x0110 => 'PX9',                                    // Model (4 bytes incl NUL -> inline)
        0x0131 => 'Snipaste',                               // Software
        0x0132 => '2026:01:02 03:04:05',                    // DateTime
    ]);
    $path = exifTempFile(exifCraftJpeg(800, 600, "Exif\x00\x00".$tiff));

    $tags = (new ImageScreeningService)->parseExifTags($path);

    expect($tags['container'])->toBe('jpeg')
        ->and($tags['exif_found'])->toBeTrue()
        ->and($tags['make'])->toBe('TestCam')
        ->and($tags['model'])->toBe('PX9')
        ->and($tags['software'])->toBe('Snipaste')
        ->and($tags['datetime'])->toBe('2026:01:02 03:04:05')
        ->and($tags['has_lens_data'])->toBeFalse();
});

test('parses a big-endian (MM) JPEG APP1 — the byte order real phones use', function () {
    $tiff = exifCraftTiff([
        0x010F => 'Xiaomi',
        0x0110 => '21121119SG',
        0x0132 => '2026:09:25 22:43:06',
    ], bigEndian: true);
    $path = exifTempFile(exifCraftJpeg(1600, 1205, "Exif\x00\x00".$tiff));

    $tags = (new ImageScreeningService)->parseExifTags($path);

    expect($tags['exif_found'])->toBeTrue()
        ->and($tags['make'])->toBe('Xiaomi')
        ->and($tags['model'])->toBe('21121119SG')
        ->and($tags['datetime'])->toBe('2026:09:25 22:43:06');
});

test('reads EXIF sub-IFD DateTimeOriginal and lens-tag presence', function () {
    $tiff = exifCraftTiff(
        [0x010F => 'LensCam'],
        bigEndian: false,
        exifSubIfd: [0x9003 => '2026:03:04 05:06:07', 0x829D => 'F2.8'],
    );
    $path = exifTempFile(exifCraftJpeg(640, 480, "Exif\x00\x00".$tiff));

    $tags = (new ImageScreeningService)->parseExifTags($path);

    expect($tags['make'])->toBe('LensCam')
        ->and($tags['datetime_original'])->toBe('2026:03:04 05:06:07')
        ->and($tags['has_lens_data'])->toBeTrue();
});

test('skips an XMP-only APP1 and still finds a following EXIF APP1', function () {
    $xmp = 'http://ns.adobe.com/xap/1.0/'."\x00".'<x:xmpmeta xmlns:x="adobe:ns:meta/"/>';
    $xmpSeg = "\xFF\xE1".pack('n', strlen($xmp) + 2).$xmp;
    $tiff = exifCraftTiff([0x010F => 'AfterXmp']);
    $exifSeg = "\xFF\xE1".pack('n', strlen("Exif\x00\x00".$tiff) + 2)."Exif\x00\x00".$tiff;

    $soi = "\xFF\xD8";
    $app0 = "\xFF\xE0".pack('n', 16)."JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
    $sof0 = "\xFF\xC0".pack('n', 11)."\x08".pack('nn', 600, 800)."\x01\x01\x11\x00";
    $path = exifTempFile($soi.$app0.$xmpSeg.$exifSeg.$sof0."\xFF\xD9");

    $tags = (new ImageScreeningService)->parseExifTags($path);

    expect($tags['exif_found'])->toBeTrue()
        ->and($tags['make'])->toBe('AfterXmp');
});

test('XMP-only JPEG reports container jpeg with no EXIF found', function () {
    $xmp = 'http://ns.adobe.com/xap/1.0/'."\x00".'<x:xmpmeta/>';
    $path = exifTempFile(exifCraftJpeg(800, 600, $xmp));

    $tags = (new ImageScreeningService)->parseExifTags($path);

    expect($tags['container'])->toBe('jpeg')
        ->and($tags['exif_found'])->toBeFalse()
        ->and($tags['make'])->toBe('');
});

test('JPEG with no APP1 at all reports container jpeg with no EXIF found', function () {
    $path = exifTempFile(exifCraftJpeg(800, 600));

    $tags = (new ImageScreeningService)->parseExifTags($path);

    expect($tags['container'])->toBe('jpeg')
        ->and($tags['exif_found'])->toBeFalse()
        ->and($tags['make'])->toBe('');
});

test('parses a PNG eXIf chunk containing a raw TIFF payload', function () {
    $tiff = exifCraftTiff([0x010F => 'PngCam', 0x0110 => 'P1']);
    $png = "\x89PNG\r\n\x1a\n"
        .exifPngChunk('IHDR', pack('NN', 1, 1)."\x08\x02\x00\x00\x00")
        .exifPngChunk('eXIf', $tiff)
        .exifPngChunk('IEND', '');
    $path = exifTempFile($png);

    $tags = (new ImageScreeningService)->parseExifTags($path);

    expect($tags['container'])->toBe('png')
        ->and($tags['exif_found'])->toBeTrue()
        ->and($tags['make'])->toBe('PngCam')
        ->and($tags['model'])->toBe('P1');
});

test('tolerates an Exif-prefixed PNG eXIf payload', function () {
    $tiff = exifCraftTiff([0x010F => 'PrefixedCam']);
    $png = "\x89PNG\r\n\x1a\n"
        .exifPngChunk('IHDR', pack('NN', 1, 1)."\x08\x02\x00\x00\x00")
        .exifPngChunk('eXIf', "Exif\x00\x00".$tiff)
        .exifPngChunk('IEND', '');
    $path = exifTempFile($png);

    $tags = (new ImageScreeningService)->parseExifTags($path);

    expect($tags['container'])->toBe('png')
        ->and($tags['exif_found'])->toBeTrue()
        ->and($tags['make'])->toBe('PrefixedCam');
});

test('parses a WebP RIFF EXIF chunk', function () {
    $tiff = exifCraftTiff([0x010F => 'WebpCam']);
    $riffBody = 'WEBP'.'EXIF'.pack('V', strlen($tiff)).$tiff;
    $webp = 'RIFF'.pack('V', strlen($riffBody)).$riffBody;
    $path = exifTempFile($webp);

    $tags = (new ImageScreeningService)->parseExifTags($path);

    expect($tags['container'])->toBe('webp')
        ->and($tags['exif_found'])->toBeTrue()
        ->and($tags['make'])->toBe('WebpCam');
});

test('unknown containers and garbage bytes return cleanly without throwing', function () {
    $svc = new ImageScreeningService;

    $garbage = exifTempFile(random_bytes(64));
    $tags = $svc->parseExifTags($garbage);
    expect($tags['container'])->toBe('unknown')
        ->and($tags['exif_found'])->toBeFalse()
        ->and($tags['make'])->toBe('');

    $empty = exifTempFile('');
    $tags = $svc->parseExifTags($empty);
    expect($tags['container'])->toBe('unknown')
        ->and($tags['exif_found'])->toBeFalse();

    $missing = sys_get_temp_dir().'/exif_does_not_exist_'.uniqid();
    $tags = $svc->parseExifTags($missing);
    expect($tags['container'])->toBe('unknown')
        ->and($tags['exif_found'])->toBeFalse();
});

test('malformed TIFF offsets never throw and yield no tags', function () {
    $svc = new ImageScreeningService;

    // Valid header, but IFD0 offset points far beyond the payload.
    $tiff = 'II'.pack('v', 42).pack('V', 4096).str_repeat("\x00", 16);
    $path = exifTempFile(exifCraftJpeg(640, 480, "Exif\x00\x00".$tiff));
    $tags = $svc->parseExifTags($path);
    expect($tags['exif_found'])->toBeTrue()
        ->and($tags['make'])->toBe('')
        ->and($tags['model'])->toBe('');

    // Truncated entry directory (count claims 30 entries, buffer has 2 bytes).
    $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 30)."\x01\x02";
    $path = exifTempFile(exifCraftJpeg(640, 480, "Exif\x00\x00".$tiff));
    $tags = $svc->parseExifTags($path);
    expect($tags['exif_found'])->toBeTrue()
        ->and($tags['make'])->toBe('');

    // Not a TIFF at all inside a valid APP1.
    $path = exifTempFile(exifCraftJpeg(640, 480, "Exif\x00\x00".'NOTTIFFDATA'));
    $tags = $svc->parseExifTags($path);
    expect($tags['exif_found'])->toBeTrue()
        ->and($tags['make'])->toBe('');
});

test('absurd ASCII counts and non-ASCII types are ignored without throwing', function () {
    $svc = new ImageScreeningService;

    // ASCII tag with a count far beyond the payload.
    $entry = pack('v', 0x010F).pack('v', 2).pack('V', 999999).pack('V', 200);
    $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).$entry.pack('V', 0).str_repeat('x', 8);
    $path = exifTempFile(exifCraftJpeg(640, 480, "Exif\x00\x00".$tiff));
    expect($svc->parseExifTags($path)['make'])->toBe('');

    // LONG-typed Make tag must not be misread as ASCII.
    $entry = pack('v', 0x010F).pack('v', 4).pack('V', 1).pack('V', 12345);
    $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).$entry.pack('V', 0);
    $path = exifTempFile(exifCraftJpeg(640, 480, "Exif\x00\x00".$tiff));
    expect($svc->parseExifTags($path)['make'])->toBe('');
});
