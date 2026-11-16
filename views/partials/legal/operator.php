<?php
/**
 * Who runs this site, for the Terms, Privacy and Refund pages.
 *
 * The same code serves the hosted site and every self-hosted copy, so the
 * operator's details come from .env (LEGAL_*), never from the code. When the
 * name, address or email is missing the pages say so instead of guessing.
 */

$legalEnv = static function (string $key): string {
    $value = getenv($key);
    return $value === false ? '' : trim((string) $value);
};

$legalOperator = [
    'name' => $legalEnv('LEGAL_OPERATOR_NAME'),
    'status' => $legalEnv('LEGAL_OPERATOR_STATUS'),
    'registration' => $legalEnv('LEGAL_REGISTRATION_NUMBER'),
    'registeredIn' => $legalEnv('LEGAL_REGISTERED_IN'),
    'officeBearers' => $legalEnv('LEGAL_OFFICE_BEARERS'),
    'address' => $legalEnv('LEGAL_ADDRESS'),
    'phone' => $legalEnv('LEGAL_PHONE'),
    'email' => $legalEnv('LEGAL_EMAIL') ?: $legalEnv('CONTACT_EMAIL'),
    'country' => $legalEnv('LEGAL_COUNTRY'),
    'informationOfficer' => $legalEnv('LEGAL_INFORMATION_OFFICER'),
    'paiaManualUrl' => $legalEnv('LEGAL_PAIA_MANUAL_URL'),
];
if (!preg_match('#^https?://#i', $legalOperator['paiaManualUrl'])) {
    $legalOperator['paiaManualUrl'] = '';
}
if ($legalOperator['informationOfficer'] === '') {
    $legalOperator['informationOfficer'] = $legalOperator['name'];
}
if (!filter_var($legalOperator['email'], FILTER_VALIDATE_EMAIL)) {
    $legalOperator['email'] = '';
}

$legalSiteName = getPublicAppName();
$legalSiteUrl = rtrim((string) APP_URL, '/');
$legalSiteHost = (string) (parse_url($legalSiteUrl, PHP_URL_HOST) ?: $legalSiteUrl);
$legalOperatorName = $legalOperator['name'] !== '' ? $legalOperator['name'] : 'the operator of ' . $legalSiteHost;
$legalCountry = $legalOperator['country'] !== '' ? $legalOperator['country'] : "the operator's country";
$legalDetailsMissing = $legalOperator['name'] === '' || $legalOperator['address'] === '' || $legalOperator['email'] === '';

// One version for all three pages; checkout stores it with the buyer's acceptance.
$legalUpdated = date('j F Y', strtotime(legalPolicyVersion()) ?: time());

$legalRouteUrl = static function (string $slug) use ($legalSiteUrl): string {
    return $legalSiteUrl . '/?page=' . rawurlencode($slug);
};
$legalEmailLink = static function () use ($legalOperator): string {
    if ($legalOperator['email'] === '') {
        return 'the contact address on this page';
    }
    $email = htmlspecialchars($legalOperator['email'], ENT_QUOTES, 'UTF-8');
    return '<a href="mailto:' . $email . '">' . $email . '</a>';
};
