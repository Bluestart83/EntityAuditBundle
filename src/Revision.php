<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SimpleThings\EntityAudit;

/**
 * Revision is returned from {@link AuditReader::getRevisions()}.
 */
class Revision
{
    public const TYPE_ADD = 'INS';
    public const TYPE_UPDATE = 'UPD';
    public const TYPE_DELETE = 'DEL';

    private bool $validated = false;

    public function __construct(private $rev, private \DateTime $timestamp, private $user_id, private ?string $user_firstName, private ?string $user_lastName, private $project)
    {
    }

    /**
     * @return int|string
     */
    public function getRev()
    {
        return $this->rev;
    }

    /**
     * @return \DateTime
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * @return string|null
     */
    public function getUserFirstname()
    {
        return $this->user_firstName;
    }

    /**
     * @return string|null
     */
    public function getUserLastName()
    {
        return $this->user_lastName;
    }

    public function getUsername()
    {
        return $this->user_username;
    }

    /**
     * @return bool
     */
    public function isValidated()
    {
        return $this->validated;
    }
}
