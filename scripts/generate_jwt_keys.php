<?php
$dir = __DIR__ . '/../config/jwt';
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
$pass = getenv('JWT_PASSPHRASE') ?: '';
$config = [
    'private_key_bits' => 4096,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];
$res = openssl_pkey_new($config);
if ($res === false) {
    fwrite(STDERR, "Failed to generate key pair. Make sure OpenSSL extension is enabled in PHP.\n");
    exit(1);
}
if ($pass !== '') {
    $exported = openssl_pkey_export($res, $privKey, $pass);
} else {
    $exported = openssl_pkey_export($res, $privKey);
}
if ($exported === false) {
    fwrite(STDERR, "Failed to export private key.\n");
    exit(1);
}
$details = openssl_pkey_get_details($res);
if ($details === false || !isset($details['key'])) {
    fwrite(STDERR, "Failed to extract public key.\n");
    exit(1);
}
$pubKey = $details['key'];
file_put_contents($dir . '/private.pem', $privKey);
file_put_contents($dir . '/public.pem', $pubKey);
chmod($dir . '/private.pem', 0600);

echo "JWT keys generated:\n";
echo "  - Private: $dir/private.pem\n";
echo "  - Public : $dir/public.pem\n";
if ($pass === '') {
    echo "Note: no passphrase was used. To use a passphrase, set the JWT_PASSPHRASE environment variable before running this script.\n";
} else {
    echo "Note: passphrase provided via JWT_PASSPHRASE environment variable.\n";
}
