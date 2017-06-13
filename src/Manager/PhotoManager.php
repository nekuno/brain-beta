<?php

namespace Manager;

use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Model\Exception\ValidationException;
use Model\GroupPhoto;
use Model\Neo4j\GraphManager;
use Model\GalleryPhoto;
use Model\Photo;
use Model\ProfilePhoto;
use Model\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PhotoManager
{

    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var string
     */
    protected $base;

    /**
     * @var string
     */
    protected $host;

    public function __construct(GraphManager $gm, $base, $host)
    {
        $this->gm = $gm;
        $this->base = $base;
        $this->host = $host;
    }

    public function createProfilePhoto()
    {
        return new ProfilePhoto($this->base, $this->host);
    }

    public function saveProfilePhoto($file, $photo)
    {
        $success = false;
        if ($photo) {
            $filename = $this->base . $file;
            $success = file_put_contents($filename, $photo);
        }

        return $success;
    }

    public function createGroupPhoto()
    {
        return new GroupPhoto($this->base, $this->host);
    }

    public function createGalleryPhoto()
    {
        return new GalleryPhoto($this->base, $this->host);
    }

    public function getAll($userId)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)<-[:PHOTO_OF]-(i:Photo)')
            ->where('u.qnoow_id = { userId }')
            ->setParameter('userId', $userId)
            ->returns('u', 'i')
            ->orderBy('i.createdAt DESC');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        $return = array();

        foreach ($result as $row) {
            $return[] = $this->build($row);
        }

        return $return;
    }

    public function getById($id)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)<-[:PHOTO_OF]-(i:Photo)')
            ->where('id(i) = { id }')
            ->setParameter('id', (integer)$id)
            ->returns('u', 'i');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Photo not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    public function create(User $user, $file)
    {

        // Validate
        $extension = $this->validate($file);

        // Save file
        $name = sha1(uniqid($user->getUsernameCanonical() . '_' . time(), true)) . '.' . $extension;
        $folder = 'uploads/gallery/' . md5($user->getId()) . '/';
        if (!is_dir($this->base . $folder)) {
            mkdir($this->base . $folder, 0775);
        }
        $path = $folder . $name;
        $saved = file_put_contents($this->base . $path, $file);

        if ($saved === false) {
            throw new ValidationException(array('photo' => 'File can not be saved'));
        }

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User {qnoow_id: { id }})')
            ->with('u')
            ->create('(u)<-[:PHOTO_OF]-(i:Photo)')
            ->set('i.createdAt = { createdAt }', 'i.path = { path }', 'i.isProfilePhoto = false')
            ->setParameters(
                array(
                    'id' => (int)$user->getId(),
                    'createdAt' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'path' => $path,
                )
            )
            ->returns('u', 'i');

        $result = $qb->getQuery()->getResultSet();

        if (count($result) < 1) {
            throw new \Exception('Could not create Photo');
        }

        $row = $result->current();

        return $this->build($row);

    }

    public function setAsProfilePhoto(Photo $photo, User $user, $xPercent = 0, $yPercent = 0, $widthPercent = 100, $heightPercent = 100)
    {
        $extension = $photo->getExtension();
        $path = 'uploads/user/' . $user->getUsernameCanonical() . '_' . time() . $extension;

        if (!is_readable($photo->getFullPath())) {
            throw new \RuntimeException(sprintf('Source image "%s" does not exists', $photo->getFullPath()));
        }

        $this->cropAndSave($photo->getFullPath(), $path, $xPercent, $yPercent, $widthPercent, $heightPercent);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User {qnoow_id: { userId }})<-[r:PHOTO_OF]-(i:Photo)')
            ->set('i.isProfilePhoto = false')
            ->with('u', 'i')
            ->match('(i)')
            ->where('id(i) = { id }')
            ->set('i.isProfilePhoto = true')
            ->set('u.photo = { path }')
            ->setParameters(array(
                'id' => $photo->getId(),
                'userId' => $user->getId(),
                'path' => $path,
            ))
            ->returns('u', 'i');

        $result = $qb->getQuery()->getResultSet();

        if (count($result) < 1) {
            throw new \Exception('Could not create Photo');
        }

        $row = $result->current();

        return $this->build($row);
    }

    public function remove($id)
    {

        $photo = $this->getById($id);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)<-[r:PHOTO_OF]-(i:Photo)')
            ->where('id(i)= { id }')
            ->setParameter('id', (integer)$id)
            ->delete('r', 'i')
            ->returns('u');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Photo not found');
        }

        $photo->delete();

    }

    public function validate($file)
    {

        $max = 5000000;
        if (strlen($file) > $max) {
            throw new ValidationException(array('photo' => array(sprintf('Max "%s" bytes file size exceed ("%s")', $max, strlen($file)))));
        }

        $extension = null;

        if (!$finfo = new \finfo(FILEINFO_MIME_TYPE)) {
            throw new ValidationException(array('photo' => array('Unable to guess file mime type')));
        }

        $mimeType = $finfo->buffer($file);

        $validTypes = array(
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
        );

        if (!isset($validTypes[$mimeType])) {
            throw new ValidationException(array('photo' => array(sprintf('Invalid mime type, possibles values are "%s".', implode('", "', array_keys($validTypes))))));
        }

        return $validTypes[$mimeType];
    }

    /**
     * @param Row $row
     * @return GalleryPhoto
     */
    protected function build(Row $row)
    {

        /* @var $node Node */
        $node = $row->offsetGet('i');

        /* @var $userNode Node */
        $userNode = $row->offsetGet('u');

        $photo = $this->createGalleryPhoto();
        $photo->setId($node->getId());
        $photo->setCreatedAt(new \DateTime($node->getProperty('createdAt')));
        $photo->setIsProfilePhoto($node->getProperty('isProfilePhoto'));
        $photo->setPath($node->getProperty('path'));
        $photo->setUserId($userNode->getProperty('qnoow_id'));

        return $photo;
    }

    public function cropAndSave($url, $path, $xPercent = 0, $yPercent = 0, $widthPercent = 100, $heightPercent = 100)
    {
        $fullPath = $this->base . $path;
        $file = file_get_contents($url);
        $size = getimagesizefromstring($file);
        $width = $size[0];
        $height = $size[1];
        $x = $width * $xPercent / 100;
        $y = $height * $yPercent / 100;
        $widthCrop = $width * $widthPercent / 100;
        $heightCrop = $height * $heightPercent / 100;
        $image = imagecreatefromstring($file);
        $crop = imagecrop($image, array('x' => $x, 'y' => $y, 'width' => $widthCrop, 'height' => $heightCrop));

        switch ($size['mime']) {
            case 'image/png':
                imagepng($crop, $fullPath);
                break;
            case 'image/jpeg':
                imagejpeg($crop, $fullPath);
                break;
            case 'image/gif':
                imagegif($crop, $fullPath);
                break;
            default:
                throw new ValidationException(array('photo' => array('Invalid mimetype')));
                break;
        }

        return $fullPath;
    }

}