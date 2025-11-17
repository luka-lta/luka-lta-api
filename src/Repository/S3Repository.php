<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\UploadedFileInterface;

class S3Repository
{
    private readonly string $awsBucket;

    public function __construct(
        private readonly S3Client $s3Client,
    ) {
        $this->awsBucket = getenv('AWS_BUCKET');
    }

    public function uploadFile(UploadedFileInterface $uploadedFile, UserId $userId): string
    {
        try {
            $stream = $uploadedFile->getStream();

            $result = $this->s3Client->putObject([
                'Bucket' => $this->awsBucket,
                'Key' => 'avatars/' . $userId->asString(),
                'Body' => $stream->getContents(),
                'ACL' => 'public-read',
            ]);
        } catch (AwsException $exception) {
            throw new ApiDatabaseException(
                'AWS S3 upload error: ' . $exception->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return $result->get('ObjectURL');
    }

    public function getAvatarImageFromS3(UserId $userId): array
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->awsBucket,
                'Key' => 'avatars/' . $userId->asString(),
            ]);
        } catch (AwsException $exception) {
            throw new ApiDatabaseException(
                'AWS S3 retrieval error: ' . $exception->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return [
            'body' => (string) $result->get('Body'),
            'contentType' => $result->get('ContentType'),
        ];
    }
}
