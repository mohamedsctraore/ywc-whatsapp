<?php

/**
 * PageEngine.php - Main component file
 *
 * This file is part of the Page component.
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Page;

use App\Yantrana\Base\BaseEngine;
use App\Yantrana\Components\Page\Interfaces\PageEngineInterface;
use App\Yantrana\Components\Page\Repositories\PageRepository;

class PageEngine extends BaseEngine implements PageEngineInterface
{
    /**
     * @var PageRepository - Page Repository
     */
    protected $pageRepository;

    /**
     * Constructor
     *
     * @param  PageRepository  $pageRepository  - Page Repository
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(PageRepository $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }

    /**
     * Page datatable source
     *
     * @return array
     *---------------------------------------------------------------- */
    public function preparePageDataTableSource()
    {
        $pageCollection = $this->pageRepository->fetchPageDataTableSource();
        // required columns for DataTables
        $requireColumns = [
            '_id',
            '_uid',
            'title',
            'slug',
            'content',
            'status',
        ];

        // prepare data for the DataTables
        return $this->dataTableResponse($pageCollection, $requireColumns);
    }

    /**
     * Page delete process
     *
     * @param  mix  $pageIdOrUid
     * @return eloquent model
     *---------------------------------------------------------------- */
    public function preparePageData($pageSlug)
    {
        // fetch the record
        $pageData = $this->pageRepository->fetchBySlugVendor($pageSlug);
        // if not found
        abort_if(__isEmpty($pageData), 404);

        return $pageData->toArray();
    }

    /**
     * Page delete process
     *
     * @param  mix  $pageIdOrUid
     * @return array
     *---------------------------------------------------------------- */
    public function processPageDelete($pageIdOrUid)
    {
        // fetch the record
        $page = $this->pageRepository->fetchIt($pageIdOrUid);
        // check if the record found
        if (__isEmpty($page)) {
            // if not found
            return $this->engineResponse(18, null, __tr('Page not found'));
        }
        // ask to delete the record
        if ($this->pageRepository->deleteIt($page)) {
            // if successful
            return $this->engineSuccessResponse([], __tr('Page deleted successfully'));
        }

        // if failed to delete
        return $this->engineFailedResponse([], __tr('Failed to delete Page'));
    }

    /**
     * Page create
     *
     * @param  array  $inputData
     * @return array
     *---------------------------------------------------------------- */
    public function processPageCreate($inputData)
    {
        if ($this->pageRepository
            ->storePage($inputData)
        ) {
            return $this->engineSuccessResponse([], __tr('Page added.'));
        }

        return $this->engineFailedResponse([], __tr('Page not added.'));
    }

    /**
     * Page prepare update data
     *
     * @param  mix  $pageIdOrUid
     * @return array
     *---------------------------------------------------------------- */
    public function preparePageUpdateData($pageIdOrUid)
    {
        $page = $this->pageRepository->fetchIt($pageIdOrUid);

        // Check if $page not exist then throw not found
        // exception
        if (__isEmpty($page)) {
            return $this->engineResponse(18, null, __tr('Page not found.'));
        }

        return $this->engineSuccessResponse($page->toArray());
    }

    /**
     * Page process update
     *
     * @param  mixed  $pageIdOrUid
     * @param  array  $inputData
     * @return array
     *---------------------------------------------------------------- */
    public function processPageUpdate($pageIdOrUid, $inputData)
    {
        $page = $this->pageRepository->fetchIt($pageIdOrUid);

        // Check if $page not exist then throw not found
        // exception
        if (__isEmpty($page)) {
            return $this->engineResponse(18, null, __tr('Page not found.'));
        }

        $updateData = [
            'title' => $inputData['title'],
            'slug' => $inputData['slug'],
            'content' => $inputData['description'],
            'status' => (isset($inputData['status']) and ($inputData['status'] == 'on')) ? 1 : 0,
            'type' => 1,
        ];

        // Check if Page updated
        if ($this->pageRepository->updatePage($page, $updateData)) {
            return $this->engineSuccessResponse([], __tr('Page updated.'));
        }

        return $this->engineResponse(14, null, __tr('Page not updated.'));
    }
}
