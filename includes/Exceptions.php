<?php

class APIException extends Exception {
    private string $errorCode;
    private int $statusCode;
    private array $meta;

    public function __construct(string $message, string $errorCode = ERROR_SERVER, int $statusCode = 400, array $meta = []) {
        parent::__construct($message);
        $this->errorCode = $errorCode !== '' ? $errorCode : ERROR_SERVER;
        $this->statusCode = $statusCode > 0 ? $statusCode : 500;
        $this->meta = $meta;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }

    public function toArray(): array {
        $payload = [
            'code' => $this->errorCode,
            'message' => $this->getMessage()
        ];

        if (!empty($this->meta)) {
            $payload['meta'] = $this->meta;
        }

        return $payload;
    }
}

