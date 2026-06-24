<?php

namespace App\CustomMailDriver\Mime\Part;

use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\DataPart;

class CustomDataPart extends DataPart
{
    private ?string $cid;

    private ?string $filename;

    /**
     * Sets the content-id of the file.
     *
     * @return $this
     */
    public function setContentId(string $cid): static
    {
        $cid = trim($cid);

        // Normalise common wrappers and remove any control characters to avoid
        // Symfony Mime throwing when it later renders Identification headers.
        $cid = trim($cid, "<> \t\n\r\0\x0B");
        $cid = preg_replace('/[\x00-\x1F\x7F]/', '', $cid) ?? '';

        if ($cid === '') {
            $this->cid = $this->generateContentId();

            return $this;
        }

        $this->cid = $cid;

        return $this;
    }

    public function getContentId(): string
    {
        if (! isset($this->cid)) {
            return $this->cid = $this->generateContentId();
        }

        return $this->cid ?: $this->cid = $this->generateContentId();
    }

    public function hasContentId(): bool
    {
        if (! isset($this->cid)) {
            return false;
        }

        return $this->cid !== null;
    }

    private function generateContentId(): string
    {
        return bin2hex(random_bytes(16)).'@symfony';
    }

    /**
     * Sets the name of the file.
     *
     * @return $this
     */
    public function setFileName(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();

        if (isset($this->cid) && $this->cid !== null) {
            $headers->setHeaderBody('Id', 'Content-ID', $this->cid);
        }

        if (isset($this->filename) && $this->filename !== null) {
            $headers->setHeaderParameter('Content-Disposition', 'filename', $this->filename);
        }

        return $headers;
    }
}
