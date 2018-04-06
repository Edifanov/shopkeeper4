<?php

namespace AppBundle\Repository;

use AppBundle\Document\Setting;
use Doctrine\ODM\MongoDB\DocumentRepository;

/**
 * SettingRepository
 */
class SettingRepository extends DocumentRepository
{

    /**
     * @param $groupName
     * @return array
     */
    public function getSettingsGroup($groupName)
    {
        return $this->findBy([
            'groupName' => $groupName
        ], ['id' => 'asc']);
    }

    /**
     * @param string $settingName
     * @param string|null $groupName
     * @return object
     */
    public function getSetting($settingName, $groupName = null)
    {
        if (null === $groupName) {
            return $this->findOneBy([
                'name' => $settingName
            ]);
        } else {
            return $this->findOneBy([
                'groupName' => $groupName,
                'name' => $settingName
            ]);
        }
    }

}
