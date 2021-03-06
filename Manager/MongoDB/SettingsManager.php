<?php
/**
 * Created by PhpStorm.
 * User: Victor
 * Date: 22/10/15
 * Time: 12:58
 */
namespace Dmishh\SettingsBundle\Manager\MongoDB;

use Dmishh\SettingsBundle\Document\Setting;
use Dmishh\SettingsBundle\Entity\SettingsOwnerInterface;
use Dmishh\SettingsBundle\Exception\WrongScopeException;
use Dmishh\SettingsBundle\Serializer\SerializerInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\VarDumper\VarDumper;

class SettingsManager extends \Dmishh\SettingsBundle\Manager\SettingsManager {

    /**
     * @param DocumentManager       $em
     * @param SerializerInterface $serializer
     * @param array               $settingsConfiguration
     */
    public function __construct(
        DocumentManager $em,
        SerializerInterface $serializer,
        array $settingsConfiguration = array()
    ) {
        $this->em = $em;
        $this->repository = $em->getRepository('Dmishh\SettingsBundle\Document\Setting');
        $this->serializer = $serializer;
        $this->settingsConfiguration = $settingsConfiguration;
    }

    /**
     * Retreives settings from repository.
     *
     * @param SettingsOwnerInterface|null $owner
     *
     * @throws \Dmishh\SettingsBundle\Exception\UnknownSerializerException
     * @return array
     */
    protected function getSettingsFromRepository(SettingsOwnerInterface $owner = null)
    {
        $settings = array();

        foreach (array_keys($this->settingsConfiguration) as $name) {
            try {
                $this->validateSetting($name, $owner);
                $settings[$name] = array_key_exists('defaultValue', $this->settingsConfiguration[$name])
                    ? $this->settingsConfiguration[$name]['defaultValue']
                    : null;
            } catch (WrongScopeException $e) {
                continue;
            }
        }

        $this->em->clear(Setting::class);

        /** @var Setting $setting */
        foreach ($this->repository->findBy(
            array('owner.id' => $owner === null ? null : $owner->getSettingIdentifier())
        ) as $setting) {
            if (array_key_exists($setting->getName(), $settings)) {
                $settings[$setting->getName()] = $this->serializer->unserialize($setting->getValue());
            }
        }

        return $settings;
    }

    protected function getNewSetting(SettingsOwnerInterface $owner = null){
        $setting = new Setting();
        if ($owner !== null) {
            $setting->setOwner($owner);
        }
        return $setting;

    }

    protected function getSettingsFromRepositoryByNames($names,SettingsOwnerInterface $owner = null){
        $settings = $this->repository->findBy(array(
                                                  'name' => array('$in' => $names),
                                                  'owner.$id' => $owner === null ? null : new \MongoId($owner->getSettingIdentifier())
                                              )
        );
        return $settings;
    }
}