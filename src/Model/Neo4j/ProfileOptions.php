<?php

namespace Model\Neo4j;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class ProfileOptions implements LoggerAwareInterface
{
    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OptionsResult
     */
    protected $result;

    public function __construct(GraphManager $gm)
    {

        $this->gm = $gm;
    }

    /**
     * @param LoggerInterface $logger
     * @return null
     */
    public function setLogger(LoggerInterface $logger)
    {

        $this->logger = $logger;
    }

    /**
     * @return OptionsResult
     */
    public function load()
    {

        $this->result = new OptionsResult();

        $options = array(
            'Alcohol' => array(
                array(
                    'id' => 'yes',
                    'name_en' => 'Yes',
                    'name_es' => 'Sí',
                ),
                array(
                    'id' => 'no',
                    'name_en' => 'No',
                    'name_es' => 'No',
                ),
                array(
                    'id' => 'occasionally',
                    'name_en' => 'Occasionally',
                    'name_es' => 'Ocasionalmente',
                ),
                array(
                    'id' => 'socially-on-parties',
                    'name_en' => 'Socially/On parties',
                    'name_es' => 'Socialmente/En fiestas',
                ),
            ),
            'CivilStatus' => array(
                array(
                    'id' => 'single',
                    'name_en' => 'Single',
                    'name_es' => 'Soltero/a',
                ),
                array(
                    'id' => 'married',
                    'name_en' => 'Married',
                    'name_es' => 'Casado/a',
                ),
                array(
                    'id' => 'open-relationship',
                    'name_en' => 'Open relationship',
                    'name_es' => 'Relación abierta',
                ),
                array(
                    'id' => 'dating-someone',
                    'name_en' => 'Dating someone',
                    'name_es' => 'Saliendo con alguien',
                ),
            ),
            'Complexion' => array(
                array(
                    'id' => 'slim',
                    'name_en' => 'Thin',
                    'name_es' => 'Delgado',
                ),
                array(
                    'id' => 'normal',
                    'name_en' => 'Average build',
                    'name_es' => 'Promedio',
                ),
                array(
                    'id' => 'fat',
                    'name_en' => 'Full-figured',
                    'name_es' => 'Voluptuoso',
                ),
                array(
                    'id' => 'overweight',
                    'name_en' => 'Overweight',
                    'name_es' => 'Con sobrepeso',
                ),
                array(
                    'id' => 'fit',
                    'name_en' => 'Fit',
                    'name_es' => 'En forma',
                ),
                array(
                    'id' => 'jacked',
                    'name_en' => 'Jacked',
                    'name_es' => 'Musculado',
                ),
                array(
                    'id' => 'little-extra',
                    'name_en' => 'A little extra',
                    'name_es' => 'Rellenito',
                ),
                array(
                    'id' => 'curvy',
                    'name_en' => 'Curvy',
                    'name_es' => 'Con curvas',
                ),
                array(
                    'id' => 'used-up',
                    'name_en' => 'Used up',
                    'name_es' => 'Huesudo',
                ),
                array(
                    'id' => 'rather-not-say',
                    'name_en' => 'Rather not say',
                    'name_es' => 'Prefiero no decir',
                ),
            ),
            'Diet' => array(
                array(
                    'id' => 'vegetarian',
                    'name_en' => 'Vegetarian',
                    'name_es' => 'Vegetariana',
                ),
                array(
                    'id' => 'vegan',
                    'name_en' => 'Vegan',
                    'name_es' => 'Vegana',
                ),
                array(
                    'id' => 'other',
                    'name_en' => 'Other',
                    'name_es' => 'Otro',
                ),
            ),
            'Drugs' => array(
                array(
                    'id' => 'antidepressants',
                    'name_en' => 'Antidepressants',
                    'name_es' => 'Antidepresivos',
                ),
                array(
                    'id' => 'cannabis',
                    'name_en' => 'Cannabis',
                    'name_es' => 'Cannabis',
                ),
                array(
                    'id' => 'caffeine',
                    'name_en' => 'Caffeine',
                    'name_es' => 'Cafeína',
                ),
                array(
                    'id' => 'dissociatives',
                    'name_en' => 'Dissociatives',
                    'name_es' => 'Disociativos',
                ),
                array(
                    'id' => 'empathogens',
                    'name_en' => 'Empathogens',
                    'name_es' => 'Empatógenos',
                ),
                array(
                    'id' => 'stimulants',
                    'name_en' => 'Stimulants',
                    'name_es' => 'Estimulantes',
                ),
                array(
                    'id' => 'psychedelics',
                    'name_en' => 'Psychedelics',
                    'name_es' => 'Psicodélicos',
                ),
                array(
                    'id' => 'opiates',
                    'name_en' => 'Opiates',
                    'name_es' => 'Opiáceos',
                ),
                array(
                    'id' => 'others',
                    'name_en' => 'Others',
                    'name_es' => 'Otros',
                ),
            ),
            'EthnicGroup' => array(
                array(
                    'id' => 'oriental',
                    'name_en' => 'Asian',
                    'name_es' => 'Asiática',
                ),
                array(
                    'id' => 'afro-american',
                    'name_en' => 'Black',
                    'name_es' => 'Negra',
                ),
                array(
                    'id' => 'caucasian',
                    'name_en' => 'White',
                    'name_es' => 'Blanca',
                ),
                array(
                    'id' => 'indian',
                    'name_en' => 'Indian',
                    'name_es' => 'India',
                ),
                array(
                    'id' => 'middle-eastern',
                    'name_en' => 'Middle Eastern',
                    'name_es' => 'Medio Oriente',
                ),
                array(
                    'id' => 'native-american',
                    'name_en' => 'Native American',
                    'name_es' => 'Indígena Americana',
                ),
                array(
                    'id' => 'pacific-islander',
                    'name_en' => 'Pacific Islander',
                    'name_es' => 'Isleño del Pacífico',
                ),
                array(
                    'id' => 'gypsy',
                    'name_en' => 'Romani/Gypsy',
                    'name_es' => 'Romaní/Gitana',
                ),
                array(
                    'id' => 'hispanic-latin',
                    'name_en' => 'Hispanic/Latin',
                    'name_es' => 'Hispana/Latina',
                ),
            ),
            'EyeColor' => array(
                array(
                    'id' => 'blue',
                    'name_en' => 'Blue',
                    'name_es' => 'Azules',
                ),
                array(
                    'id' => 'brown',
                    'name_en' => 'Brown',
                    'name_es' => 'Castaños',
                ),
                array(
                    'id' => 'black',
                    'name_en' => 'Black',
                    'name_es' => 'Negros',
                ),
                array(
                    'id' => 'green',
                    'name_en' => 'Green',
                    'name_es' => 'Verdes',
                ),
                array(
                    'id' => 'other',
                    'name_en' => 'Other',
                    'name_es' => 'Otro',
                ),
            ),
            'Gender' => array(
                array(
                    'id' => 'male',
                    'name_en' => 'Male',
                    'name_es' => 'Masculino',
                ),
                array(
                    'id' => 'female',
                    'name_en' => 'Female',
                    'name_es' => 'Femenino',
                ),
            ),
            'DescriptiveGender' => array(
                array(
                    'id' => 'man',
                    'name_en' => 'Man',
                    'name_es' => 'Hombre',
                ),
                array(
                    'id' => 'woman',
                    'name_en' => 'Woman',
                    'name_es' => 'Mujer',
                ),
                array(
                    'id' => 'agender',
                    'name_en' => 'Agender',
                    'name_es' => 'Agénero',
                ),
                array(
                    'id' => 'androgynous',
                    'name_en' => 'Androgynous',
                    'name_es' => 'Andrógino',
                ),
                array(
                    'id' => 'bigender',
                    'name_en' => 'Bigender',
                    'name_es' => 'Bigénero',
                ),
                array(
                    'id' => 'cis-man',
                    'name_en' => 'Cis Man',
                    'name_es' => 'Cis Hombre',
                ),
                array(
                    'id' => 'cis-woman',
                    'name_en' => 'Cis Woman',
                    'name_es' => 'Cis Mujer',
                ),
                array(
                    'id' => 'genderfluid',
                    'name_en' => 'Genderfluid',
                    'name_es' => 'Género fluido',
                ),
                array(
                    'id' => 'genderqueer',
                    'name_en' => 'Genderqueer',
                    'name_es' => 'Genderqueer',
                ),
                array(
                    'id' => 'gender-nonconforming',
                    'name_en' => 'Gender nonconforming',
                    'name_es' => 'Género no conforme',
                ),
                array(
                    'id' => 'hijra',
                    'name_en' => 'Hijra',
                    'name_es' => 'Hijra',
                ),
                array(
                    'id' => 'intersex',
                    'name_en' => 'Intersex',
                    'name_es' => 'Intersex',
                ),
                array(
                    'id' => 'non-binary',
                    'name_en' => 'Non-binary',
                    'name_es' => 'No binario',
                ),
                array(
                    'id' => 'pangender',
                    'name_en' => 'Pangender',
                    'name_es' => 'Pangénero',
                ),
                array(
                    'id' => 'transfeminine',
                    'name_en' => 'Transfeminine',
                    'name_es' => 'Transfeminino',
                ),
                array(
                    'id' => 'transgender',
                    'name_en' => 'Transgender',
                    'name_es' => 'Transgénero',
                ),
                array(
                    'id' => 'transmasculine',
                    'name_en' => 'Transmasculine',
                    'name_es' => 'Transmasculino',
                ),
                array(
                    'id' => 'transsexual',
                    'name_en' => 'Transsexual',
                    'name_es' => 'Transexual',
                ),
                array(
                    'id' => 'trans-man',
                    'name_en' => 'Trans Man',
                    'name_es' => 'Trans Hombre',
                ),
                array(
                    'id' => 'trans-woman',
                    'name_en' => 'Trans Woman',
                    'name_es' => 'Trans Mujer',
                ),
                array(
                    'id' => 'two-spirit',
                    'name_en' => 'Two Spirit',
                    'name_es' => 'Dos Espíritus',
                ),
                array(
                    'id' => 'other',
                    'name_en' => 'Other',
                    'name_es' => 'Otros',
                ),
            ),
            'Objective' => array(
                array(
                    'id' => 'work',
                    'name_en' => 'Work',
                    'name_es' => 'Trabajo',
                ),
                array(
                    'id' => 'play',
                    'name_en' => 'Play',
                    'name_es' => 'Jugar',
                ),
                array(
                    'id' => 'human-contact',
                    'name_en' => 'Human contact',
                    'name_es' => 'Contacto humano',
                ),
                array(
                    'id' => 'share-space',
                    'name_en' => 'Share space',
                    'name_es' => 'Compartir espacio',
                ),
                array(
                    'id' => 'travel',
                    'name_en' => 'Travel',
                    'name_es' => 'Viajar',
                ),
            ),
            'Sons' => array(
                array(
                    'id' => 'yes',
                    'name_en' => 'Have kids(s)',
                    'name_es' => 'Tengo hijos',
                ),
                array(
                    'id' => 'no',
                    'name_en' => "Doesn't have kids",
                    'name_es' => 'No tengo hijos',
                ),
            ),
            'HairColor' => array(
                array(
                    'id' => 'black',
                    'name_en' => 'Black',
                    'name_es' => 'Negro',
                ),
                array(
                    'id' => 'brown',
                    'name_en' => 'Brown',
                    'name_es' => 'Castaño',
                ),
                array(
                    'id' => 'blond',
                    'name_en' => 'Blond',
                    'name_es' => 'Rubio',
                ),
                array(
                    'id' => 'red',
                    'name_en' => 'Red',
                    'name_es' => 'Rojo',
                ),
                array(
                    'id' => 'gray-or-white',
                    'name_en' => 'Gray or White',
                    'name_es' => 'Gris o Blanco',
                ),
                array(
                    'id' => 'other',
                    'name_en' => 'Other',
                    'name_es' => 'Otro',
                ),
            ),
            'Income' => array(
                array(
                    'id' => 'less-than-us-12-000-year',
                    'name_en' => 'Less than US$12,000/year',
                    'name_es' => 'Menos de 12.000 US$/año',
                ),
                array(
                    'id' => 'between-us-12-000-and-us-24-000-year',
                    'name_en' => 'Between US$12,000 and US$24,000/year',
                    'name_es' => 'Entre 12.000 y 24.000 US$/año',
                ),
                array(
                    'id' => 'more-than-us-24-000-year',
                    'name_en' => 'More than US$24,000/year',
                    'name_es' => 'Más de 24.000 US$/año',
                ),
            ),
            'Orientation' => array(
                array(
                    'id' => 'heterosexual',
                    'name_en' => 'Straight',
                    'name_es' => 'Hetero',
                ),
                array(
                    'id' => 'homosexual',
                    'name_en' => 'Gay',
                    'name_es' => 'Homo',
                ),
                array(
                    'id' => 'bisexual',
                    'name_en' => 'Bisexual',
                    'name_es' => 'Bisexual',
                ),
                array(
                    'id' => 'asexual',
                    'name_en' => 'Asexual',
                    'name_es' => 'Asexual',
                ),
                array(
                    'id' => 'demisexual',
                    'name_en' => 'Demisexual',
                    'name_es' => 'Demisexual',
                ),
                array(
                    'id' => 'heteroflexible',
                    'name_en' => 'Heteroflexible',
                    'name_es' => 'Heteroflexible',
                ),
                array(
                    'id' => 'homoflexible',
                    'name_en' => 'Homoflexible',
                    'name_es' => 'Homoflexible',
                ),
                array(
                    'id' => 'lesbian',
                    'name_en' => 'Lesbian',
                    'name_es' => 'Lesbi',
                ),
                array(
                    'id' => 'pansexual',
                    'name_en' => 'Pansexual',
                    'name_es' => 'Pansexual',
                ),
                array(
                    'id' => 'queer',
                    'name_en' => 'Queer',
                    'name_es' => 'Queer',
                ),
                array(
                    'id' => 'questioning',
                    'name_en' => 'Questioning',
                    'name_es' => 'Cuestionandomelo',
                ),
                array(
                    'id' => 'sapiosexual',
                    'name_en' => 'Sapiosexual',
                    'name_es' => 'Sapiosexual',
                ),
            ),
            'Pets' => array(
                array(
                    'id' => 'cat',
                    'name_en' => 'Cat',
                    'name_es' => 'Gato',
                ),
                array(
                    'id' => 'dog',
                    'name_en' => 'Dog',
                    'name_es' => 'Perro',
                ),
                array(
                    'id' => 'other',
                    'name_en' => 'Other',
                    'name_es' => 'Otras',
                ),
            ),
            'RelationshipInterest' => array(
                array(
                    'id' => 'friendship',
                    'name_en' => 'Friendship',
                    'name_es' => 'Amistad',
                ),
                array(
                    'id' => 'relation',
                    'name_en' => 'Relation',
                    'name_es' => 'Relación',
                ),
                array(
                    'id' => 'open-relation',
                    'name_en' => 'Open Relation',
                    'name_es' => 'Relación Abierta',
                ),
            ),
            'Smoke' => array(
                array(
                    'id' => 'yes',
                    'name_en' => 'Yes',
                    'name_es' => 'Sí',
                ),
                array(
                    'id' => 'no-but-i-tolerate-it',
                    'name_en' => 'No, but I tolerate it',
                    'name_es' => 'No, pero lo toleraría',
                ),
                array(
                    'id' => 'no-and-i-hate-it',
                    'name_en' => 'No, and cannot stand it',
                    'name_es' => 'No, y no lo soporto',
                ),
            ),
            'InterfaceLanguage' => array(
                array(
                    'id' => 'es',
                    'name_en' => 'Español',
                    'name_es' => 'Español',
                ),
                array(
                    'id' => 'en',
                    'name_en' => 'English',
                    'name_es' => 'English',
                ),
            ),
            'ZodiacSign' => array(
                array(
                    'id' => 'capricorn',
                    'name_en' => 'Capricorn',
                    'name_es' => 'Capricornio',
                ),
                array(
                    'id' => 'sagittarius',
                    'name_en' => 'Sagittarius',
                    'name_es' => 'Sagitario',
                ),
                array(
                    'id' => 'scorpio',
                    'name_en' => 'Scorpio',
                    'name_es' => 'Escorpio',
                ),
                array(
                    'id' => 'libra',
                    'name_en' => 'Libra',
                    'name_es' => 'Libra',
                ),
                array(
                    'id' => 'virgo',
                    'name_en' => 'Virgo',
                    'name_es' => 'Virgo',
                ),
                array(
                    'id' => 'leo',
                    'name_en' => 'Leo',
                    'name_es' => 'Leo',
                ),
                array(
                    'id' => 'cancer',
                    'name_en' => 'Cancer',
                    'name_es' => 'Cáncer',
                ),
                array(
                    'id' => 'gemini',
                    'name_en' => 'Gemini',
                    'name_es' => 'Géminis',
                ),
                array(
                    'id' => 'taurus',
                    'name_en' => 'Taurus',
                    'name_es' => 'Tauro',
                ),
                array(
                    'id' => 'aries',
                    'name_en' => 'Aries',
                    'name_es' => 'Aries',
                ),
                array(
                    'id' => 'pisces',
                    'name_en' => 'Pisces',
                    'name_es' => 'Piscis',
                ),
                array(
                    'id' => 'aquarius',
                    'name_en' => 'Aquarius',
                    'name_es' => 'Acuario',
                ),
            ),
            'Religion' => array(
                array(
                    'id' => 'agnosticism',
                    'name_en' => 'Agnosticism',
                    'name_es' => 'Agnóstico',
                ),
                array(
                    'id' => 'atheism',
                    'name_en' => 'Atheism',
                    'name_es' => 'Ateo',
                ),
                array(
                    'id' => 'christianity',
                    'name_en' => 'Christianity',
                    'name_es' => 'Cristiano',
                ),
                array(
                    'id' => 'judaism',
                    'name_en' => 'Judaism',
                    'name_es' => 'Judio',
                ),
                array(
                    'id' => 'catholicism',
                    'name_en' => 'Catholicism',
                    'name_es' => 'Católico',
                ),
                array(
                    'id' => 'islam',
                    'name_en' => 'Islam',
                    'name_es' => 'Musulmán',
                ),
                array(
                    'id' => 'hinduism',
                    'name_en' => 'Hinduism',
                    'name_es' => 'Hinduista',
                ),
                array(
                    'id' => 'buddhism',
                    'name_en' => 'Buddhism',
                    'name_es' => 'Budista',
                ),
                array(
                    'id' => 'sikh',
                    'name_en' => 'Sikh',
                    'name_es' => 'Sikh',
                ),
                array(
                    'id' => 'kopimism',
                    'name_en' => 'Kopimism',
                    'name_es' => 'Kopimista',
                ),
                array(
                    'id' => 'other',
                    'name_en' => 'Other',
                    'name_es' => 'Otra',
                ),
            ),
        );

        foreach ($options as $type => $values) {
            foreach ($values as $value) {
                $id = $value['id'];
                $names = array(
                    'name_es' => $value['name_es'],
                    'name_en' => $value['name_en'],
                );
                $this->processOption($type, $id, $names);
            }
        }

        return $this->result;
    }

    /**
     * @param $type
     * @param $id
     * @param $names
     * @throws \Exception
     */
    public function processOption($type, $id, $names)
    {

        $this->result->incrementTotal();

        if ($this->optionExists($type, $id)) {

            if ($this->optionExists($type, $id, $names)) {

                $this->logger->info(sprintf('Skipping, Already exists ProfileOption:%s id: "%s", name_en: "%s", name_es: "%s"', $type, $id, $names['name_en'], $names['name_es']));

            } else {

                $this->result->incrementUpdated();
                $this->logger->info(sprintf('Updating ProfileOption:%s id: "%s", name_en: "%s", name_es: "%s"', $type, $id, $names['name_en'], $names['name_es']));
                $parameters = array('type' => $type, 'id' => $id);
                $parameters = array_merge($parameters, $names);
                $cypher = "MATCH (o:ProfileOption) WHERE {type} IN labels(o) AND o.id = {id} SET o.name_en = {name_en}, o.name_es = {name_es} RETURN o;";

                $query = $this->gm->createQuery($cypher, $parameters);
                $query->getResultSet();
            }

        } else {

            $this->result->incrementCreated();
            $this->logger->info(sprintf('Creating ProfileOption:%s id: "%s", name_en: "%s", name_es: "%s"', $type, $id, $names['name_en'], $names['name_es']));
            $parameters = array('id' => $id);
            $parameters = array_merge($parameters, $names);
            $cypher = "CREATE (:ProfileOption:" . $type . " { id: {id}, name_en: {name_en}, name_es: {name_es} })";

            $query = $this->gm->createQuery($cypher, $parameters);
            $query->getResultSet();
        }
    }

    /**
     * @param $type
     * @param $id
     * @param array $names
     * @return boolean
     * @throws \Exception
     */
    public function optionExists($type, $id, $names = array())
    {
        $parameters = array('type' => $type, 'id' => $id);
        $cypher = "MATCH (o:ProfileOption) WHERE {type} IN labels(o) AND o.id = {id}\n";
        if (!empty($names)) {
            $parameters = array_merge($parameters, $names);
            $cypher .= "AND o.name_es = {name_es} AND o.name_en = {name_en}\n";
        }
        $cypher .= "RETURN o;";

        $query = $this->gm->createQuery($cypher, $parameters);
        $result = $query->getResultSet();

        return count($result) > 0;
    }
} 