<?php
    namespace AmazonS3\Lib;

    use Aws\Credentials\Credentials;
    use Aws\S3\S3Client;
    use Cake\Core\Exception\Exception;
    use Cake\Core\InstanceConfigTrait;
    use Cake\Filesystem\File;
    use InvalidArgumentException;

    class AmazonS3Exception extends Exception {
    }

    class AmazonS3 {

        use InstanceConfigTrait;

        protected $_defaultConfig = [
            'version' => 'latest',
            'region'  => 'sa-east-1',
            'http'    => [
                'verify' => false
            ]
        ];

        /**
         * Your Amazon S3 SDK client
         *
         * @var string
         */
        public $S3Client = '';

        /**
         * Amazon S3 endpoint
         *
         * @var string
         */
        public $endpoint = 's3.amazonaws.com';

        /**
         * Absolute path to ur local file
         *
         * @var string
         */
        public $localPath = '';

        /**
         * Array of information about our local file
         *
         * @var array
         */
        public $info = [];

        /**
         * File class
         *
         * @var File
         */
        public $File;

        /**
         * Full path of the file to be added to the bucket
         *
         * @var string
         */
        public $file;

        /**
         * Constructor
         *
         * @param array $config Config array in format array('accessKey' => {accessKey} ,
         *                      'secretKey' => {secretKey},
         *                      'bucket' => {bucket})
         *
         * @return void
         * @throws \Cake\Core\Exception\Exception
         * @author Rob Mcvey
         **/
        public function __construct(array $config) {
            $this->config($config);

            $this->config(
                'credentials',
                new Credentials(
                    $this->config('accessKey'), $this->config('secretKey')
                )
            );

            $this->_configDelete('accessKey');
            $this->_configDelete('secretKey');

            $this->S3Client = new S3Client($this->config());
        }

        /**
         * Put a local file in an S3 bucket
         *
         * @param      $localPath
         * @param null $remoteDir
         *
         * @return string Public Url of the uploaded file
         * @throws \Cake\Core\Exception\Exception
         * @throws \InvalidArgumentException
         */
        public function put($localPath, $remoteDir = null) {
            // Base filename
            $filename = basename($localPath);

            // File remote/local files
            $this->checkLocalPath($localPath);
            $this->checkFile($filename);

            // Set the target and local path to where we're saving
            $this->file = $filename;

            $this->checkRemoteDir($remoteDir);
            $this->getLocalFileInfo();

            // Build the HTTP request
            $this->S3Client->putObject(
                [
                    'Bucket'      => $this->config('bucket'),
                    'Key'         => $this->file,
                    'Body'        => $this->File->read(),
                    'ContentType' => $this->File->mime(), // prevents downloading the file by force
                    'ACL'         => 'public-read'
                ]
            );
        }

        /**
         * Fetch a remote file from S3 and save locally
         *
         * @param string $remoteFile
         * @param string $localPath
         */
        public function get($remoteFile, $localPath) {
            $this->checkFile($remoteFile);

            if (empty($localPath)) {
                throw new InvalidArgumentException(__('You must set a localPath (i.e where to save the file)'));
            }

            $response = $this->S3Client->getObject(
                [
                    'Bucket' => $this->config('bucket'),
                    'Key'    => $remoteFile
                ]
            );

            // Write file locally
            $this->File = new File($localPath . DS . $remoteFile, true);
            $this->File->write($response['Body']);
        }

        /**
         * Delete a remote file from S3
         *
         * @param $remoteFile
         *
         * TODO not working. The response is fine (204) but the file is not deleted
         *
         * @throws \Cake\Core\Exception\Exception
         */
        public function delete($remoteFile) {
            $this->checkFile($remoteFile);

            $this->S3Client->deleteObject(
                [
                    'Bucket' => $this->config('bucket'),
                    'Key'    => $remoteFile
                ]
            );
        }

        /**
         * Returns the public URL of a file
         *
         * @return string
         * @author Rob Mcvey
         */
        public function publicUrl($file, $ssl = false) {
            $scheme = 'http';
            if ($ssl) {
                $scheme .= 's';
            }
            // Replace any preceeding slashes
            $file = preg_replace("/^\//", '', $file);

            return sprintf('%s://%s.%s/%s', $scheme, $this->config('bucket'), $this->endpoint, $file);
        }

        /**
         * Check we have a file string
         *
         * @return void
         * @author Rob Mcvey
         */
        public function checkFile($file) {
            if (empty($file)) {
                throw new InvalidArgumentException(__('You must specify the file you are fetching (e.g remote_dir/file.txt)'));
            }
        }

        /**
         * Check our local path
         *
         * @param $localPath
         *
         * @throws \InvalidArgumentException
         */
        public function checkLocalPath($localPath) {
            if (empty($localPath)) {
                throw new InvalidArgumentException(__('You must set a localPath (i.e where to save the file)'));
            }
            if (!file_exists($localPath)) {
                throw new InvalidArgumentException(__('The localPath you set does not exist'));
            }

            $this->localPath = $localPath;
        }

        /**
         * Removes preceding/trailing slashes from a remote dir target and builds $this->file again
         *
         * @param $remoteDir
         */
        public function checkRemoteDir($remoteDir) {
            // Is the target a directory? Remove preceending and trailing /
            if ($remoteDir) {
                $remoteDir = preg_replace(array("/^\//", "/\/$/"), "", $remoteDir);
                $this->file = $remoteDir . '/' . $this->file;
            }
        }

        /**
         * Get local file info (uses CakePHP Utility/File class)
         *
         * @return array
         * @author Rob Mcvey
         **/
        public function getLocalFileInfo() {
            $this->File = new File($this->localPath);
            $this->info = $this->File->info();

            return $this->info;
        }
    }
