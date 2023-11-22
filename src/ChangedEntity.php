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
 * @phpstan-template T of object
 */
class ChangedEntity
{

    /**
     * @param array<string, int|string> $id
     *
     * @phpstan-param class-string<T> $className
     * @phpstan-param T $entity
     */
    public function __construct(private string $className, private array $id, private string $revType, private object $entity, private ?int $rev = null, private ?\DateTime $timestamp = null,
                                private $user_id=null, private ?string $user_username=null, private ?string $user_firstName=null, private ?string $user_lastName=null)
    {
    }

    /**
     * @phpstan-return class-string<T>
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @return array<string, int|string>
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \DateTime
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @return string
     */
    public function getRevisionType()
    {
        return $this->revType;
    }

    /**
     * @return object
     *
     * @phpstan-return T
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return object
     */
     public function getRevision()
     {
         return $this->rev;
     }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function getUserFirstname()
    {
        return $this->user_firstName;
    }

    public function getUserLastName()
    {
        return $this->user_lastName;
    }

    public function getUserUsername()
    {
        return $this->user_username;
    }

    public function getUserDisplayName()
    {
        if( ($this->user_firstName!='' && $this->user_firstName!=null) || ($this->user_lastName!='' && $this->user_lastName!=null)) {

            $parts = array();
            if(($this->user_firstName!='' && $this->user_firstName!=null)) {
                $parts[] = $this->user_firstName;
            }
            if(($this->user_lastName!='' && $this->user_lastName!=null)) {
                $parts[] = $this->user_lastName;
            }
            return implode(' ', $parts);
        }
        else {
            return $this->user_username;
        }
    }
}
