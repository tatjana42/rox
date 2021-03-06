<?php

namespace App\Form\CustomDataClass;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\PersistentCollection;
use SearchModel;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class SearchFormRequest.
 *
 * @SuppressWarnings(PHPMD)
 */
class SearchFormRequest
{
    /**
     * @var string
     */
    public $location;

    /**
     * @var integer
     */
    public $location_geoname_id;

    /**
     * @var float
     */
    public $location_latitude;

    /**
     * @var float
     */
    public $location_longitude;

    /**
     * @var float
     *
     * @Assert\NotBlank(groups={"map-search"})
     */
    public $ne_latitude;

    /**
     * @var float
     *
     * @Assert\NotBlank(groups={"map-search"})
     */
    public $ne_longitude;

    /**
     * @var float
     *
     * @Assert\NotBlank(groups={"map-search"})
     */
    public $sw_latitude;

    /**
     * @var float
     *
     * @Assert\NotBlank(groups={"map-search"})
     */
    public $sw_longitude;

    /** @var boolean */
    public $accommodation_anytime = true;

    /** @var boolean */
    public $accommodation_dependonrequest = true;

    /** @var boolean */
    public $accommodation_neverask = false;

    /** @var boolean */
    public $showmap = false;

    /**
     * @var integer
     *
     * @Assert\Choice({ -1, 0, 5, 10, 15, 20, 50, 100, 200})
     */
    public $distance = 20;

    /**
     * @var integer
     */
    public $showOnMap = false;

    /** @var integer */
    public $can_host = 1;

    /** @var integer */
    public $page = 1;

    /** @var PersistentCollection */
    public $groups;

    /** @var PersistentCollection */
    public $languages;

    /** @var integer */
    public $min_age;

    /** @var integer */
    public $max_age;

    /** @var string */
    public $gender;

    /** @var boolean */
    public $inactive;

    /** @var boolean */
    public $offerdinner;

    /** @var boolean */
    public $offertour;

    /** @var boolean */
    public $accessible;

    /** @var string */
    public $keywords;

    /** @var integer */
    public $order = 6;

    /** @var integer */
    public $items = 20;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * SearchFormRequest constructor.
     *
     * @param ObjectManager $em
     */
    public function __construct(ObjectManager $em)
    {
        $this->em = $em;
    }

    /**
     * @Assert\IsTrue(message="search.location.invalid")
     */
    public function isLocationValid()
    {
        // Check if the form was submitted through the map javascript
        $showOnMap = boolval($this->showOnMap);
        if (true === $showOnMap) {
            return true;
        }

        if (empty($this->location)) {
            // Empty location is never correct
            return false;
        }
        // Check if $location_geoname_id, $location_latitude and $location_longitude are set
        if (0 !== $this->location_geoname_id && $this->location_latitude && $this->location_longitude) {
            // The searchpicker set all necessary information
            return true;
        }

        // Searchpicker didn't get a chance to set the location information
        // \todo Try to find one based on the entered information in the location field

        return false;
    }

    public static function fromRequest(Request $request, ObjectManager $em)
    {
        $searchFormRequest = new self($em);
        $data = [];
        if ($request->query->has('tiny')) {
            $data = $request->query->get('tiny');
        }
        if ($request->query->has('home')) {
            $data = $request->query->get('home');
        }
        if ($request->query->has('search')) {
            $data = $request->query->get('search');
        }

        $searchFormRequest->location = self::_getElement($data, 'location', '');
        $searchFormRequest->accommodation_anytime = self::_getElement($data, 'accommodation_anytime', null);
        $searchFormRequest->accommodation_dependonrequest = self::_getElement($data, 'accommodation_dependonrequest', null);
        $searchFormRequest->accommodation_neverask = self::_getElement($data, 'accommodation_neverask', null);
        $searchFormRequest->can_host = self::_getElement($data, 'can_host', 1);
        $searchFormRequest->distance = self::_getElement($data, 'distance', 20);
        $searchFormRequest->keywords = self::_getElement($data, 'keywords', '');
        $searchFormRequest->page = $request->query->get('page', 1);
        $searchFormRequest->groups = self::_getElement($data, 'groups', []);
        $searchFormRequest->languages = self::_getElement($data, 'languages', []);
        $searchFormRequest->inactive = self::_getElement($data, 'inactive', false);
        $searchFormRequest->location_geoname_id = self::_getElement($data, 'location_geoname_id', null);
        $searchFormRequest->location_latitude = self::_getElement($data, 'location_latitude', null);
        $searchFormRequest->location_longitude = self::_getElement($data, 'location_longitude', null);
        $searchFormRequest->min_age = self::_getElement($data, 'min_age', null);
        $searchFormRequest->max_age = self::_getElement($data, 'max_age', null);
        $searchFormRequest->gender = self::_getElement($data, 'gender', null);
        $searchFormRequest->order = self::_getElement($data, 'order', SearchModel::ORDER_ACCOM);
        $searchFormRequest->items = self::_getElement($data, 'items', 10);
        $searchFormRequest->showmap = self::_getElement($data, 'showmap', false);
        $searchFormRequest->showOnMap = self::_getElement($data, 'showOnMap', false);
        $searchFormRequest->offerdinner = self::_getElement($data, 'offerdinner', false);
        $searchFormRequest->offertour = self::_getElement($data, 'offertour', false);
        $searchFormRequest->accessible = self::_getElement($data, 'accessible', false);
        $searchFormRequest->ne_latitude = self::_getElement($data, 'ne-latitude', null);
        $searchFormRequest->ne_longitude = self::_getElement($data, 'ne-longitude', null);
        $searchFormRequest->sw_latitude = self::_getElement($data, 'sw-latitude', null);
        $searchFormRequest->sw_longitude = self::_getElement($data, 'sw-longitude', null);

        return $searchFormRequest;
    }

    public static function determineValidationGroups(FormInterface $form)
    {
        $data = $form->getData();
        $showOnMap = boolval($data->showOnMap);
        if (true === $showOnMap) {
            return ['map-search'];
        }

        return ['text-search'];
    }

    private static function _getElement($data, $index, $default)
    {
        return (isset($data[$index])) ? $data[$index] : $default;
    }
}
