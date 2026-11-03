<?php
// includes/PollinationsImageEngine.php
// Dedicated Pollinations.ai Image Engine for Venue & Conceptual Catering Staging
// Supports FLUX.3, Flux.1 Schnell, and Z-Image Turbo with Server-Side Auth Security

class PollinationsImageEngine {
    private $apiKey;
    private $timeout;
    private $lastError = null;
    private $lastUsedModel = null;

    // Supported official models mapping
    const MODEL_FLUX_SCHNELL = 'black-forest-labs/flux.1-schnell';
    const MODEL_FLUX_3       = 'black-forest-labs/flux-3-image';
    const MODEL_Z_IMAGE_TURBO = 'tongyi-mai/z-image-turbo';

    public function __construct($apiKey = '', $timeout = 45) {
        $this->apiKey = !empty($apiKey) ? trim($apiKey) : (defined('POLLINATIONS_API_KEY') ? POLLINATIONS_API_KEY : '');
        $this->timeout = $timeout;
    }

    /**
     * Resolves user/config model string into the official Pollinations model identifier
     */
    public static function resolveModelId($model) {
        $m = strtolower(trim($model));
        switch ($m) {
            case 'flux.3':
            case 'flux-3':
            case 'flux3':
            case 'black-forest-labs/flux-3-image':
                return self::MODEL_FLUX_3;

            case 'z-image-turbo':
            case 'z-image':
            case 'zimage':
            case 'turbo':
            case 'tongyi-mai/z-image-turbo':
                return self::MODEL_Z_IMAGE_TURBO;

            case 'flux.1-schnell':
            case 'flux.1':
            case 'flux-schnell':
            case 'schnell':
            case 'flux':
            case 'black-forest-labs/flux.1-schnell':
            default:
                return self::MODEL_FLUX_SCHNELL;
        }
    }

    /**
     * Human-readable label for the model
     */
    public static function getModelLabel($modelId) {
        switch ($modelId) {
            case self::MODEL_FLUX_3:
                return 'FLUX.3 Image';
            case self::MODEL_Z_IMAGE_TURBO:
                return 'Z-Image Turbo';
            case self::MODEL_FLUX_SCHNELL:
            default:
                return 'Flux.1 Schnell';
        }
    }

    /**
     * Core venue image generation call
     *
     * @param string $prompt       The staging prompt text
     * @param string $model        Model name ('flux', 'flux-3', 'z-image-turbo')
     * @param int    $width        Output image width (e.g. 1280 or 1024)
     * @param int    $height       Output image height (e.g. 720 or 768)
     * @param array  $options      Additional parameters (seed, nologo, etc.)
     * @return string|false        Raw binary image data on success, false on failure
     */
    public function generateVenueImage($prompt, $model = 'flux', $width = 1280, $height = 720, $options = []) {
        $targetModel = self::resolveModelId($model);
        $this->lastUsedModel = $targetModel;
        $this->lastError = null;

        $seed = !empty($options['seed']) ? intval($options['seed']) : rand(10000, 999999);
        $nologo = isset($options['nologo']) ? ($options['nologo'] ? 'true' : 'false') : 'true';

        $url = "https://gen.pollinations.ai/image/" . rawurlencode($prompt)
             . "?model=" . urlencode($targetModel)
             . "&width=" . intval($width)
             . "&height=" . intval($height)
             . "&nologo=" . $nologo
             . "&seed=" . $seed;

        $headers = [];
        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $rawImage = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && !empty($rawImage) && strlen($rawImage) > 5000) {
            return $rawImage;
        }

        // Resilient Fallback: If FLUX.3 requires credits (HTTP 402) or times out, auto-fallback to Flux.1 Schnell or Z-Image Turbo
        if ($targetModel === self::MODEL_FLUX_3 && ($httpCode === 402 || $httpCode >= 500 || empty($rawImage))) {
            $fallbackModel = self::MODEL_FLUX_SCHNELL;
            $this->lastUsedModel = $fallbackModel;

            $fallbackUrl = "https://gen.pollinations.ai/image/" . rawurlencode($prompt)
                         . "?model=" . urlencode($fallbackModel)
                         . "&width=" . intval($width)
                         . "&height=" . intval($height)
                         . "&nologo=" . $nologo
                         . "&seed=" . $seed;

            $ch2 = curl_init($fallbackUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            if (!empty($headers)) {
                curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
            }
            $rawImage2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            if ($httpCode2 === 200 && !empty($rawImage2) && strlen($rawImage2) > 5000) {
                return $rawImage2;
            }
        }

        $this->lastError = "Pollinations API returned HTTP {$httpCode}" . ($curlErr ? " ({$curlErr})" : "");
        return false;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getLastUsedModel() {
        return $this->lastUsedModel;
    }
}

/**
 * Step 1: Format the text for prompt precision (as requested by user)
 *
 * @param string $customerVenue  Venue name or detected structure
 * @param string $desiredStyle   Theme / aesthetic style (linens, flowers, tableware)
 * @param string $desiredTime    Lighting or time of day
 * @param string $specificNotes  Specific layout, stage, architectural notes
 * @param array  $cateringExtras Additional catering details (guest count, tables, buffet)
 * @return string
 */
function generateAccurateVenuePrompt($customerVenue, $desiredStyle = '', $desiredTime = '', $specificNotes = '', $cateringExtras = []) {
    $promptParts = [];

    // Core scene definition
    $venueStr = !empty($customerVenue) ? trim($customerVenue) : "Luxury Event Venue";
    $promptParts[] = "Professional wide-angle 16:9 interior and event photography of a luxury catering staging inside {$venueStr}.";

    // Style & Decor
    if (!empty($desiredStyle)) {
        $promptParts[] = "Theme & Decor: " . rtrim(trim($desiredStyle), '.') . ".";
    }

    // Catering Setup details (Guest tables, buffet stations)
    $pax = !empty($cateringExtras['pax']) ? intval($cateringExtras['pax']) : 0;
    $tablesCount = $pax > 0 ? max(1, (int)ceil($pax / 10)) : 10;
    $colors = !empty($cateringExtras['colors']) ? trim($cateringExtras['colors']) : '';

    $cateringDesc = "Catering layout: {$tablesCount} round 10-seater guest banquet tables"
                  . (!empty($colors) ? " dressed in {$colors} premium floor-length linens" : "")
                  . ", matching Chiavari chairs, elegant floral centerpieces, crystal stemware, and neatly folded cloth napkins. "
                  . "Perimeter dual-sided buffet lines with mirror-polished stainless roll-top chafing dishes and gourmet presentation.";
    $promptParts[] = $cateringDesc;

    // Time & Lighting
    if (!empty($desiredTime)) {
        $promptParts[] = "Lighting & Atmosphere: " . rtrim(trim($desiredTime), '.') . ".";
    } else {
        $promptParts[] = "Lighting: Warm celebration spotlights, ambient fairy lights, and natural daylight highlights.";
    }

    // Specific architectural notes
    if (!empty($specificNotes)) {
        $promptParts[] = "Architectural preservation: " . rtrim(trim($specificNotes), '.') . ", with original room architecture, ceiling fixtures, and flooring intact.";
    }

    $promptParts[] = "Photorealistic 8k resolution, architectural digest staging, pristine depth of field, immaculate event presentation.";

    return implode(' ', $promptParts);
}
