<?php

namespace App\CustomMailDriver\Mime\Crypto;

use App\CustomMailDriver\Mime\Part\EncryptedPart;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mime\Email;

class OpenPGPEncrypter
{
    protected $gnupg = null;

    protected $usesProtectedHeaders;

    /**
     * The signing hash algorithm. 'MD5', SHA1, or SHA256. SHA256 (the default) is highly recommended
     * unless you need to deal with an old client that doesn't support it. SHA1 and MD5 are
     * currently considered cryptographically weak.
     *
     * This is apparently not supported by the PHP GnuPG module.
     *
     * @type string
     */
    protected $micalg = 'SHA256';

    protected $recipientKey = null;

    protected $recipientPublicKey = null;

    /**
     * The fingerprint of the key that will be used to sign the email. Populated either with
     * autoAddSignature or addSignature.
     *
     * @type string
     */
    protected $signingKey;

    /**
     * An associative array of keyFingerprint=>passwords to decrypt secret keys (if needed).
     * Populated by calling addKeyPassphrase. Pointless at the moment because the GnuPG module in
     * PHP doesn't support decrypting keys with passwords. The command line client does, so this
     * method stays for now.
     *
     * @type array
     */
    protected $keyPassphrases = [];

    /**
     * Specifies the home directory for the GnuPG keyrings. By default this is the user's home
     * directory + /.gnupg, however when running on a web server (eg: Apache) the home directory
     * will likely not exist and/or not be writable. Set this by calling setGPGHome before calling
     * any other encryption/signing methods.
     *
     * @var string
     */
    protected $gnupgHome = null;

    public function __construct($signingKey = null, $recipientKey = null, $gnupgHome = null, $usesProtectedHeaders = false)
    {
        $this->signingKey = $signingKey;
        $this->recipientKey = $recipientKey;
        $this->gnupgHome = $gnupgHome;
        $this->usesProtectedHeaders = $usesProtectedHeaders;
        $this->initGNUPG();
    }

    /**
     * @param  string  $micalg
     */
    public function setMicalg($micalg)
    {
        $this->micalg = $micalg;
    }

    /**
     * @param  null  $passPhrase
     *
     * @throws RuntimeException
     */
    public function addSignature($identifier, $keyFingerprint = null, $passPhrase = null)
    {
        if (! $keyFingerprint) {
            $keyFingerprint = $this->getKey($identifier, 'sign');
        }
        $this->signingKey = $keyFingerprint;

        if ($passPhrase) {
            $this->addKeyPassphrase($keyFingerprint, $passPhrase);
        }
    }

    /**
     * @throws RuntimeException
     */
    public function addKeyPassphrase($identifier, $passPhrase)
    {
        $keyFingerprint = $this->getKey($identifier, 'sign');
        $this->keyPassphrases[$keyFingerprint] = $passPhrase;
    }

    /**
     * @param  Email  $email
     * @return $this
     *
     * @throws Exception
     */
    public function encrypt(Email $symfonyMessage): Email
    {
        $originalMessage = clone $symfonyMessage;
        // Clone to ensure headers are not altered if encryption fails
        $message = clone $symfonyMessage;

        $headers = $message->getPreparedHeaders();

        $boundary = strtr(base64_encode(random_bytes(6)), '+/', '-_');

        $headers->setHeaderBody('Parameterized', 'Content-Type', 'multipart/encrypted');
        $headers->setHeaderParameter('Content-Type', 'protocol', 'application/pgp-encrypted');
        $headers->setHeaderParameter('Content-Type', 'boundary', $boundary);

        $message->setHeaders($headers);

        // If the email does not have any text part then we need to add a text/plain legacy display part
        if ($this->usesProtectedHeaders && is_null($originalMessage->getTextBody())) {
            $originalMessage->text($headers->get('Subject')->toString());
        }

        if ($this->usesProtectedHeaders) {
            $headers->setHeaderBody('Text', 'Subject', '...');
        }

        $originalBody = PgpMimeEncryptionPlaintext::fromEmail($originalMessage, $this->usesProtectedHeaders);

        // Create encrypted body from original message
        $encryptedBody = $this->pgpEncryptAndSignString($originalBody, $this->recipientKey, $this->signingKey);

        // Fixes DKIM signature incorrect body hash for custom domains
        $body = "This is an OpenPGP/MIME encrypted message (RFC 4880 and 3156)\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/pgp-encrypted\r\n";
        $body .= "Content-Description: PGP/MIME version identification\r\n\r\n";
        $body .= "Version: 1\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/octet-stream; name=\"encrypted.asc\"\r\n";
        $body .= "Content-Description: OpenPGP encrypted message\r\n";
        $body .= "Content-Disposition: inline; filename=\"encrypted.asc\"\r\n\r\n";
        $body .= $encryptedBody."\r\n\r\n";
        $body .= "--{$boundary}--";

        return $message->setBody(new EncryptedPart($body));
    }

    /**
     * @param  Email  $email
     * @return $this
     *
     * @throws Exception
     */
    public function encryptInline(Email $symfonyMessage): Email
    {
        if (! $this->signingKey) {
            foreach ($symfonyMessage->getFrom() as $key => $value) {
                $this->addSignature($this->getKey($key, 'sign'));
            }
        }

        if (! $this->signingKey) {
            throw new RuntimeException('Signing has been enabled, but no signature has been added. Use autoAddSignature() or addSignature()');
        }

        if (! $this->recipientKey) {
            throw new RuntimeException('Encryption has been enabled, but no recipients have been added. Use autoAddRecipients() or addRecipient()');
        }

        $body = $symfonyMessage->getTextBody() ?? '';

        $text = $this->pgpEncryptAndSignString($body, $this->recipientKey, $this->signingKey);

        $headers = $symfonyMessage->getPreparedHeaders();
        $headers->setHeaderBody('Parameterized', 'Content-Type', 'text/plain');
        $headers->setHeaderParameter('Content-Type', 'charset', 'utf-8');
        $symfonyMessage->setHeaders($headers);

        return $symfonyMessage->setBody(new EncryptedPart($text));
    }

    /**
     * @throws RuntimeException
     */
    protected function initGNUPG()
    {
        if (! class_exists('gnupg')) {
            throw new RuntimeException('PHPMailerPGP requires the GnuPG class');
        }

        putenv('GNUPGHOME='.$this->resolveGnupgHome());

        if (! $this->gnupg) {
            $this->gnupg = new \gnupg;
        }

        $this->gnupg->seterrormode(\gnupg::ERROR_EXCEPTION);
    }

    protected function resolveGnupgHome(): string
    {
        $home = config('anonaddy.gnupg_home') ?: ($this->gnupgHome ?? '');

        if (Str::startsWith($home, '~')) {
            $resolved = $this->resolveUserHomeDirectory().substr($home, 1);
            $this->guardResolvableGnupgHome($resolved);

            return $resolved;
        }

        if ($home === '') {
            throw new RuntimeException(
                'GnuPG home directory is not configured. Set ANONADDY_GNUPGHOME in .env or pass a homedir to OpenPGPEncrypter.'
            );
        }

        $this->guardResolvableGnupgHome($home);

        return $home;
    }

    protected function resolveUserHomeDirectory(): string
    {
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? '');

        if ($home === '' || $home === '/') {
            if (function_exists('posix_getpwuid')) {
                $passwd = posix_getpwuid(posix_geteuid());
                $home = $passwd['dir'] ?? '';
            }
        }

        if ($home === '' || $home === '/') {
            throw new RuntimeException(
                'GnuPG home directory cannot be resolved because HOME is not set for this process. '.
                'Set ANONADDY_GNUPGHOME in .env to an absolute path such as /home/vagrant/.gnupg, '.
                'or ensure queue workers export HOME.'
            );
        }

        return $home;
    }

    protected function guardResolvableGnupgHome(string $path): void
    {
        if ($path === '/.gnupg' || str_starts_with($path, '/.gnupg/')) {
            throw new RuntimeException(
                'GnuPG home directory resolved to an invalid path ('.$path.'). '.
                'Set ANONADDY_GNUPGHOME in .env to an absolute path such as /home/vagrant/.gnupg.'
            );
        }
    }

    /**
     * @param  $plaintext
     * @param  $keyFingerprints
     * @return string
     *
     * @throws RuntimeException
     */
    protected function pgpEncryptAndSignString($text, $keyFingerprint, $signingKeyFingerprint)
    {
        $recipientArgs = $this->buildEncryptRecipientArgs($keyFingerprint);

        return $this->runGpgCommand(array_merge(
            ['--local-user', $signingKeyFingerprint, '--encrypt', '--sign'],
            $recipientArgs
        ), $text);
    }

    protected function pgpEncryptString($text, $keyFingerprint)
    {
        return $this->runGpgCommand(array_merge(
            ['--encrypt'],
            $this->buildEncryptRecipientArgs($keyFingerprint)
        ), $text);
    }

    /**
     * @return list<string>
     */
    protected function buildEncryptRecipientArgs(string $keyFingerprint): array
    {
        $this->ensureRecipientPublicKeyImported();

        $args = [];

        foreach ($this->getSubkeyFingerprints($keyFingerprint, 'encrypt') as $fingerprint) {
            $args[] = '-r';
            $args[] = $fingerprint.'!';
        }

        return $args;
    }

    /**
     * @param  list<string>  $args
     *
     * @throws RuntimeException
     */
    protected function runGpgCommand(array $args, string $input): string
    {
        $gnupgHome = $this->resolveGnupgHome();

        $command = array_merge([
            'gpg',
            '--homedir', $gnupgHome,
            '--armor',
            '--batch',
            '--yes',
            '--trust-model', 'always',
        ], $args);

        $inputFile = $this->writeTemporaryInputFile($input);

        try {
            $process = proc_open(
                $command,
                [
                    0 => ['file', $inputFile, 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                null,
                ['GNUPGHOME' => $gnupgHome],
            );

            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start gpg process');
            }

            stream_set_timeout($pipes[1], 120);
            stream_set_timeout($pipes[2], 120);

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0 || $stdout === '') {
                throw new RuntimeException('GPG command failed: '.trim($stderr ?: 'unknown error'));
            }

            return $stdout;
        } finally {
            @unlink($inputFile);
        }
    }

    /**
     * @return string
     *
     * @throws RuntimeException
     */
    protected function getKey($identifier, $purpose)
    {
        return $this->getSubkeyFingerprints($identifier, $purpose)[0];
    }

    /**
     * @return list<string>
     *
     * @throws RuntimeException
     */
    protected function getSubkeyFingerprints(string $identifier, string $purpose): array
    {
        if ($this->recipientPublicKey) {
            $fingerprints = $this->getSubkeyFingerprintsFromArmoredKey($this->recipientPublicKey, $purpose);

            if ($fingerprints !== []) {
                return $fingerprints;
            }
        }

        $keys = $this->gnupg->keyinfo($identifier);
        $fingerprints = [];

        foreach ($keys as $key) {
            if ($this->isUsablePrimaryKey($key)) {
                foreach ($key['subkeys'] as $subKey) {
                    if ($this->isValidKey($subKey, $purpose)) {
                        $fingerprints[] = $subKey['fingerprint'];
                    }
                }
            }
        }

        if ($fingerprints === []) {
            throw new RuntimeException(sprintf('Unable to find an active key to %s for %s, try importing keys first', $purpose, $identifier));
        }

        return $fingerprints;
    }

    protected function ensureRecipientPublicKeyImported(): void
    {
        if (! $this->recipientPublicKey) {
            return;
        }

        $gnupgHome = $this->resolveGnupgHome();
        $keyFile = $this->writeTemporaryKeyFile($this->recipientPublicKey);

        try {
            Process::timeout(30)
                ->env(['GNUPGHOME' => $gnupgHome])
                ->run(['gpg', '--homedir', $gnupgHome, '--batch', '--import', $keyFile]);
        } finally {
            @unlink($keyFile);
        }
    }

    /**
     * @return list<string>
     */
    protected function getSubkeyFingerprintsFromArmoredKey(string $armoredKey, string $purpose): array
    {
        $keyFile = $this->writeTemporaryKeyFile($armoredKey);

        try {
            $result = Process::timeout(30)
                ->run(['gpg', '--with-colons', '--show-keys', $keyFile]);
        } finally {
            @unlink($keyFile);
        }

        if ($result->failed()) {
            throw new RuntimeException('Unable to read public key: '.trim($result->errorOutput() ?: 'unknown error'));
        }

        return $this->parseColonKeyFingerprints($result->output(), $purpose);
    }

    /**
     * @return list<string>
     */
    protected function parseColonKeyFingerprints(string $output, string $purpose): array
    {
        $fingerprints = [];
        $capability = $purpose === 'encrypt' ? 'e' : 's';
        $awaitingFingerprint = false;

        foreach (explode("\n", $output) as $line) {
            if (str_starts_with($line, 'sub:')) {
                $fields = explode(':', $line);
                $validity = $fields[1] ?? '';
                $capabilities = $fields[11] ?? '';

                $awaitingFingerprint = ! in_array($validity, ['r', 'e'], true)
                    && str_contains($capabilities, $capability);

                continue;
            }

            if ($awaitingFingerprint && str_starts_with($line, 'fpr:')) {
                $fields = explode(':', $line);
                $fingerprint = $fields[9] ?? '';

                if ($fingerprint !== '') {
                    $fingerprints[] = $fingerprint;
                }

                $awaitingFingerprint = false;
            }
        }

        return $fingerprints;
    }

    protected function writeTemporaryKeyFile(string $armoredKey): string
    {
        return $this->writeTemporaryInputFile($armoredKey);
    }

    protected function writeTemporaryInputFile(string $contents): string
    {
        $inputFile = tempnam(sys_get_temp_dir(), 'gpg-in-');
        file_put_contents($inputFile, $contents);
        chmod($inputFile, 0600);

        return $inputFile;
    }

    protected function isUsablePrimaryKey(array $key): bool
    {
        return ! ($key['disabled'] || $key['expired'] || $key['revoked']);
    }

    protected function isValidKey($key, $purpose)
    {
        return ! ($key['disabled'] || $key['expired'] || $key['revoked'] || ($purpose === 'sign' && ! $key['can_sign']) || ($purpose === 'encrypt' && ! $key['can_encrypt']));
    }
}
