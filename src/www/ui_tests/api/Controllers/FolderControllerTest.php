<?php
/***************************************************************
 * Copyright (C) 2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Controller for folder queries
 */

namespace Fossology\UI\Api\Controllers
{

  /**
   * Override to common-folder function GetFolderArray
   */
  function GetFolderArray($a, &$b)
  {
    if ($a == 2) {
      $b[2] = "root";
      $b[3] = "root-child1";
      $b[4] = "root-child2";
    } else {
      $b[$a] = "singlefolder";
    }
  }

  /**
   * Override to common-folder function FolderGetName
   */
  function FolderGetName($folderId)
  {
    return "$folderId-folder";
  }

  /**
   * Override to common-folder function Folder2Path
   */
  function Folder2Path($folderId)
  {
    $folderList = array();
    $folderList[] = ["folder_pk" => 2, "folder_name" => FolderGetName(2)];
    $folderList[] = ["folder_pk" => 3, "folder_name" => FolderGetName(3)];
    return $folderList;
  }
}

namespace Fossology\UI\Api\Test\Controllers
{

  use Mockery as M;
  use Fossology\UI\Api\Helper\DbHelper;
  use Fossology\UI\Api\Helper\RestHelper;
  use Fossology\UI\Api\Controllers\FolderController;
  use Fossology\Lib\Dao\FolderDao;
  use Fossology\Lib\Data\Folder\Folder;
  use Slim\Http\Response;
  use Fossology\UI\Api\Models\Info;
  use Fossology\UI\Api\Models\InfoType;
  use Slim\Http\Headers;
  use Slim\Http\Request;
  use Slim\Http\Uri;
  use Slim\Http\Body;

  /**
   * @class FolderControllerTest
   * @brief Test for FolderController
   */
  class FolderControllerTest extends \PHPUnit\Framework\TestCase
  {

    /**
     * @var DbHelper $dbHelper
     * DbHelper mock
     */
    private $dbHelper;
    /**
     * @var FolderDao $folderDao
     * FolderDao mock
     */
    private $folderDao;
    /**
     * @var RestHelper $restHelper
     * RestHelper mock
     */
    private $restHelper;
    /**
     * @var FolderController $folderController
     * FolderController object to test
     */
    private $folderController;
    /**
     * @var integer $userId
     * Current user ID to test
     */
    private $userId;
    /**
     * @var folder_create $folderPlugin
     * Folder plugin object to mock
     */
    private $folderPlugin;
    /**
     * @var admin_folder_delete $deletePlugin
     * Delagent folder delete object to mock
     */
    private $deletePlugin;
    /**
     * @var folder_properties $folderPropertiesPlugin
     * Folder properties plugin to mock
     */
    private $folderPropertiesPlugin;
    /**
     * @var AdminContentMove $folderContentPlugin
     * Folder copy/move plugin object to mock
     */
    private $folderContentPlugin;

    /**
     * @var integer $assertCountBefore
     * Assertions before running tests
     */
    private $assertCountBefore;

    /**
     * @brief Setup test objects
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp() : void
    {
      global $container;
      $this->userId = 2;
      $container = M::mock('ContainerBuilder');
      $this->dbHelper = M::mock(DbHelper::class);
      $this->restHelper = M::mock(RestHelper::class);
      $this->folderDao = M::mock(FolderDao::class);
      $this->folderPlugin = M::mock('folder_create');
      $this->deletePlugin = M::mock('admin_folder_delete');
      $this->folderPropertiesPlugin = M::mock('folder_properties');
      $this->folderContentPlugin = M::mock('content_move');

      $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
      $this->restHelper->shouldReceive('getFolderDao')->andReturn($this->folderDao);
      $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('folder_create'))->andReturn($this->folderPlugin);
      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('admin_folder_delete'))->andReturn($this->deletePlugin);
      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('folder_properties'))
        ->andReturn($this->folderPropertiesPlugin);
      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('content_move'))
        ->andReturn($this->folderContentPlugin);

      $container->shouldReceive('get')->withArgs(array(
        'helper.restHelper'))->andReturn($this->restHelper);
      $this->folderController = new FolderController($container);
      $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    }

    /**
     * @brief Remove test objects
     * @see PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown() : void
    {
      $this->addToAssertionCount(
        \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
      M::close();
    }

    /**
     * Helper function to generate pseudo Folder objects
     *
     * @param integer $id Folder ID to generate
     * @return NULL|\Fossology\Lib\Data\Folder\Folder
     */
    public function getFolder($id)
    {
      $name = "";
      switch ($id) {
        case 2: $name = "root";break;
        case 3: $name = "root-child1";break;
        case 4: $name = "root-child2";break;
        case -1: return null;
        default: $name = "singlefolder";
      }
      return new Folder($id, $name, "", 1);
    }

    /**
     * Helper function to get pseudo parent id of given folder
     *
     * @param integer $id Folder id to get parent
     * @return NULL|number
     */
    public function getFolderParent($id)
    {
      $pid = null;
      if ($id == 3 || $id == 4) {
        $pid = 2;
      } elseif ($id > 4) {
        $pid = $id - 1;
      }
      return $pid;
    }

    /**
     * Helper function to get JSON array from response
     *
     * @param Response $response
     * @return array Decoded response
     */
    private function getResponseJson($response)
    {
      $response->getBody()->seek(0);
      return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @test
     * -# Test to get all folders accessible by user by calling
     *    FolderController::getFolders()
     * -# Check if the response is array of folders
     */
    public function testGetAllFolders()
    {
      $rootFolder = new Folder(2, "root", "", 2);
      $this->folderDao->shouldReceive('getRootFolder')->withArgs(array(2))
        ->andReturn($rootFolder);
      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $this->folderDao->shouldReceive('getFolderParentId')
        ->andReturnUsing([$this, 'getFolderParent']);
      $expectedFoldersList = [];
      for ($i = 2; $i <= 4; $i ++) {
        $folder = $this->getFolder($i);
        $parentId = $this->getFolderParent($i);
        $folderModel = new \Fossology\UI\Api\Models\Folder($folder->getId(),
          $folder->getName(), $folder->getDescription(), $parentId);
        $expectedFoldersList[] = $folderModel->getArray();
      }
      $actualResponse = $this->folderController->getFolders(null,
        new Response(), []);
      $this->assertEquals(200, $actualResponse->getStatusCode());
      $this->assertEquals($expectedFoldersList,
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test to get specific folder from FolderController::getFolders()
     * -# Check if the response is a single object of Folder
     */
    public function testGetSpecificFolders()
    {
      $folderId = 3;
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($folderId))->andReturn(true);
      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $this->folderDao->shouldReceive('getFolderParentId')
        ->andReturnUsing([$this, 'getFolderParent']);
      $folder = $this->getFolder($folderId);
      $parentId = $this->getFolderParent($folderId);
      $folderModel = new \Fossology\UI\Api\Models\Folder($folder->getId(),
        $folder->getName(), $folder->getDescription(), $parentId);
      $expectedFoldersList = $folderModel->getArray();
      $actualResponse = $this->folderController->getFolders(null,
        new Response(), ['id' => $folderId]);
      $this->assertEquals(200, $actualResponse->getStatusCode());
      $this->assertEquals($expectedFoldersList,
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test to check a 404 response of invalid folder
     * -# Call FolderController::getFolders() for invalid folder
     * -# Check for a 404 response
     */
    public function testGetInvalidFolder()
    {
      $folderId = -1;
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($folderId))->andReturn(false);
      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $expectedResponse = new Info(404, "Folder id $folderId does not exists",
        InfoType::ERROR);
      $actualResponse = $this->folderController->getFolders(null,
        new Response(), ['id' => $folderId]);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test to check inaccessible folder's response on
     *    FolderController::getFolders()
     * -# Check for 403 response
     */
    public function testGetInAccessibleFolder()
    {
      $folderId = 3;
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($folderId))->andReturn(false);
      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $expectedResponse = new Info(403, "Folder id $folderId is not accessible",
        InfoType::ERROR);
      $actualResponse = $this->folderController->getFolders(null,
        new Response(), ['id' => $folderId]);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Check for FolderController::createFolder()
     * -# Check for 201 response with folder id
     */
    public function testCreateFolder()
    {
      $parentFolder = 2;
      $folderName = "root-child1";
      $folderDescription = "Description";
      $folderId = 5;
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($parentFolder, $this->userId))->andReturn(true);
      $this->folderPlugin->shouldReceive('create')
        ->withArgs(array($parentFolder, $folderName, $folderDescription))
        ->andReturn(1);
      $this->folderDao->shouldReceive('getFolderId')
        ->withArgs(array($folderName, $parentFolder))->andReturn($folderId);
      $requestHeaders = new Headers();
      $requestHeaders->set('parentFolder', $parentFolder);
      $requestHeaders->set('folderName', $folderName);
      $requestHeaders->set('folderDescription', $folderDescription);
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("POST", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();
      $actualResponse = $this->folderController->createFolder($request,
        $response, []);
      $expectedResponse = new Info(201, $folderId, InfoType::INFO);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Check for inaccessible parent on FolderController::createFolder()
     * -# Check for 403 response
     */
    public function testCreateFolderParentNotAccessible()
    {
      $parentFolder = 2;
      $folderName = "root-child1";
      $folderDescription = "Description";
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($parentFolder, $this->userId))->andReturn(false);
      $requestHeaders = new Headers();
      $requestHeaders->set('parentFolder', $parentFolder);
      $requestHeaders->set('folderName', $folderName);
      $requestHeaders->set('folderDescription', $folderDescription);
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("POST", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();
      $actualResponse = $this->folderController->createFolder($request,
        $response, []);
      $expectedResponse = new Info(403, "Parent folder can not be accessed!",
        InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Check for duplicate folder response from
     *    FolderController::createFolder()
     * -# Check for 200 response
     */
    public function testCreateFolderDuplicateNames()
    {
      $parentFolder = 2;
      $folderName = "root-child1";
      $folderDescription = "Description";
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($parentFolder, $this->userId))->andReturn(true);
      $this->folderPlugin->shouldReceive('create')
        ->withArgs(array($parentFolder, $folderName, $folderDescription))
        ->andReturn(4);
      $requestHeaders = new Headers();
      $requestHeaders->set('parentFolder', $parentFolder);
      $requestHeaders->set('folderName', $folderName);
      $requestHeaders->set('folderDescription', $folderDescription);
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("POST", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();
      $actualResponse = $this->folderController->createFolder($request,
        $response, []);
      $expectedResponse = new Info(200, "Folder $folderName already exists!",
        InfoType::INFO);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for FolderController::deleteFolder()
     * -# Check for 202 response
     */
    public function testDeleteFolder()
    {
      $folderId = 3;
      $folderName = \Fossology\UI\Api\Controllers\FolderGetName($folderId);
      $this->folderDao->shouldReceive('getFolder')
        ->withArgs(array($folderId))->andReturn($this->getFolder($folderId));
      $this->deletePlugin->shouldReceive('Delete')
        ->withArgs(array("2 $folderId", $this->userId))->andReturnNull();
      $actualResponse = $this->folderController->deleteFolder(null,
        new Response(), ["id" => $folderId]);
      $expectedResponse = new Info(202, "Folder, \"$folderName\" deleted.",
        InfoType::INFO);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test if folder is invalid on FolderController::deleteFolder()
     * -# Check for 404 response
     */
    public function testDeleteFolderInvalidFolder()
    {
      $folderId = 0;
      $this->folderDao->shouldReceive('getFolder')
        ->withArgs(array($folderId))->andReturnNull();
      $actualResponse = $this->folderController->deleteFolder(null,
        new Response(), ["id" => $folderId]);
      $expectedResponse = new Info(404, "Folder id not found!",
        InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test to delete inaccessible folder on FolderController::deleteFolder()
     * -# Check for 403 response
     */
    public function testDeleteFolderNoAccess()
    {
      $folderId = 3;
      $errorText = "No access to delete this folder";
      $this->folderDao->shouldReceive('getFolder')
        ->withArgs(array($folderId))->andReturn($this->getFolder($folderId));
      $this->deletePlugin->shouldReceive('Delete')
        ->withArgs(array("2 $folderId", $this->userId))
        ->andReturn($errorText);
      $actualResponse = $this->folderController->deleteFolder(null,
        new Response(), ["id" => $folderId]);
      $expectedResponse = new Info(403, $errorText, InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for FolderController::editFolder()
     * -# Check for 200 reponse
     */
    public function testEditFolder()
    {
      $folderId = 3;
      $folderName = "new name";
      $folderDescription = "new description";
      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($folderId, $this->userId))->andReturn(true);
      $this->folderPropertiesPlugin->shouldReceive('Edit')
        ->withArgs(array($folderId, $folderName, $folderDescription));
      $requestHeaders = new Headers();
      $requestHeaders->set('name', $folderName);
      $requestHeaders->set('description', $folderDescription);
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PATCH", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();
      $actualResponse = $this->folderController->editFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(200, 'Folder "' . \Fossology\UI\Api\Controllers\FolderGetName($folderId) .
        '" updated.', InfoType::INFO);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Check FolderController::editFolder() on non-existing folder
     * -# Check for 404 response
     */
    public function testEditFolderNotExists()
    {
      $folderId = 8;
      $folderName = "new name";
      $folderDescription = "new description";
      $this->folderDao->shouldReceive('getFolder')->andReturnNull();
      $requestHeaders = new Headers();
      $requestHeaders->set('name', $folderName);
      $requestHeaders->set('description', $folderDescription);
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PATCH", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();
      $actualResponse = $this->folderController->editFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(404, "Folder id not found!", InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for inaccessible folder on FolderController::editFolder()
     * -# Check for 403 response
     */
    public function testEditFolderNotAccessible()
    {
      $folderId = 3;
      $folderName = "new name";
      $folderDescription = "new description";
      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($folderId, $this->userId))->andReturn(false);
      $requestHeaders = new Headers();
      $requestHeaders->set('name', $folderName);
      $requestHeaders->set('description', $folderDescription);
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PATCH", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();
      $actualResponse = $this->folderController->editFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(403, "Folder is not accessible!",
        InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for copy action on FolderController::copyFolder()
     * -# Check for 202 response
     */
    public function testCopyFolder()
    {
      $folderId = 3;
      $parentId = 2;
      $folderContentPk = 5;
      $folderName = \Fossology\UI\Api\Controllers\FolderGetName($folderId);
      $parentFolderName = \Fossology\UI\Api\Controllers\FolderGetName($parentId);

      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array(M::anyOf($folderId, "$parentId"),
          $this->userId))->andReturn(true);
      $this->folderDao->shouldReceive('getFolderContentsId')
        ->withArgs(array($folderId, 1))->andReturn($folderContentPk);
      $this->folderContentPlugin->shouldReceive('copyContent')
        ->withArgs(array([$folderContentPk], $parentId, true))->andReturn("");
      $requestHeaders = new Headers();
      $requestHeaders->set('parent', $parentId);
      $requestHeaders->set('action', "copy");
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();

      $actualResponse = $this->folderController->copyFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(202,
        "Folder \"$folderName\" copy(ed) under \"$parentFolderName\".",
        InfoType::INFO);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for move action on FolderController::copyFolder()
     * -# Check for 202 response
     */
    public function testMoveFolder()
    {
      $folderId = 3;
      $parentId = 2;
      $folderContentPk = 5;
      $folderName = \Fossology\UI\Api\Controllers\FolderGetName($folderId);
      $parentFolderName = \Fossology\UI\Api\Controllers\FolderGetName($parentId);

      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array(M::anyOf($folderId, "$parentId"),
          $this->userId))->andReturn(true);
      $this->folderDao->shouldReceive('getFolderContentsId')
        ->withArgs(array($folderId, 1))->andReturn($folderContentPk);
      $this->folderContentPlugin->shouldReceive('copyContent')
        ->withArgs(array([$folderContentPk], $parentId, false))->andReturn("");
      $requestHeaders = new Headers();
      $requestHeaders->set('parent', $parentId);
      $requestHeaders->set('action', "move");
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();

      $actualResponse = $this->folderController->copyFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(202,
        "Folder \"$folderName\" move(ed) under \"$parentFolderName\".",
        InfoType::INFO);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for invalid folder copy on FolderController::copyFolder()
     * -# Check for 404 response
     */
    public function testCopyFolderNotFound()
    {
      $folderId = 3;
      $parentId = 2;

      $this->folderDao->shouldReceive('getFolder')->withArgs(array($folderId))
        ->andReturnNull();
      $requestHeaders = new Headers();
      $requestHeaders->set('parent', $parentId);
      $requestHeaders->set('action', "copy");
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();

      $actualResponse = $this->folderController->copyFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(404, "Folder id not found!",
        InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for invalid parent copy on FolderController::copyFolder()
     * -# Check for 404 response
     */
    public function testCopyParentFolderNotFound()
    {
      $folderId = 3;
      $parentId = 2;

      $this->folderDao->shouldReceive('getFolder')->withArgs(array($folderId))
        ->andReturn($this->getFolder($folderId));
      $this->folderDao->shouldReceive('getFolder')->withArgs(array($parentId))
        ->andReturnNull();
      $requestHeaders = new Headers();
      $requestHeaders->set('parent', $parentId);
      $requestHeaders->set('action', "copy");
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();

      $actualResponse = $this->folderController->copyFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(404, "Parent folder not found!",
        InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for inaccessible folder on FolderController::copyFolder()
     * -# Check for 403 response
     */
    public function testCopyFolderNotAccessible()
    {
      $folderId = 3;
      $parentId = 2;

      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($folderId, $this->userId))->andReturn(false);
      $requestHeaders = new Headers();
      $requestHeaders->set('parent', $parentId);
      $requestHeaders->set('action', "copy");
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();

      $actualResponse = $this->folderController->copyFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(403, "Folder is not accessible!",
        InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for inaccessible parent folder on FolderController::copyFolder()
     * -# Check for 403 response
     */
    public function testCopyParentFolderNotAccessible()
    {
      $folderId = 3;
      $parentId = 2;

      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array($folderId, $this->userId))->andReturn(true);
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array("$parentId", $this->userId))->andReturn(false);
      $requestHeaders = new Headers();
      $requestHeaders->set('parent', $parentId);
      $requestHeaders->set('action', "copy");
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();

      $actualResponse = $this->folderController->copyFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(403, "Parent folder is not accessible!",
        InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for invalid action on FolderController::copyFolder()
     * -# Check for 400 response
     */
    public function testCopyFolderInvalidAction()
    {
      $folderId = 3;
      $parentId = 2;

      $this->folderDao->shouldReceive('getFolder')
        ->andReturnUsing([$this, 'getFolder']);
      $this->folderDao->shouldReceive('isFolderAccessible')
        ->withArgs(array(M::anyOf($folderId, "$parentId"),
          $this->userId))->andReturn(true);
      $requestHeaders = new Headers();
      $requestHeaders->set('parent', $parentId);
      $requestHeaders->set('action', "somethingrandom");
      $body = new Body(fopen('php://temp', 'r+'));
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $response = new Response();

      $actualResponse = $this->folderController->copyFolder($request,
        $response, ["id" => $folderId]);
      $expectedResponse = new Info(400, "Action can be one of [copy,move]!",
        InfoType::ERROR);
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
    }
  }
}
