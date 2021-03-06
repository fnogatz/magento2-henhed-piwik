<?php
/**
 * Copyright 2016-2018 Henrik Hedelund
 * Copyright 2020      Falco Nogatz
 *
 * This file is part of Chessio_Matomo.
 *
 * Chessio_Matomo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Chessio_Matomo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Chessio_Matomo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Chessio\Matomo\Test\Unit\Observer;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * Test for \Chessio\Matomo\Observer\SearchResultObserver
 *
 */
class SearchResultObserverTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Search result observer (test subject) instance
     *
     * @var \Chessio\Matomo\Observer\SearchResultObserver $_observer
     */
    protected $_observer;

    /**
     * Matomo tracker mock object
     *
     * @var \PHPUnit_Framework_MockObject_MockObject $_trackerMock
     */
    protected $_trackerMock;

    /**
     * Matomo data helper mock object
     *
     * @var \PHPUnit_Framework_MockObject_MockObject $_dataHelperMock
     */
    protected $_dataHelperMock;

    /**
     * Layout mock object
     *
     * @var \PHPUnit_Framework_MockObject_MockObject $_layoutMock
     */
    protected $_layoutMock;

    /**
     * Search query mock object
     *
     * @var \PHPUnit_Framework_MockObject_MockObject $_queryMock
     */
    protected $_queryMock;

    /**
     * Matomo block mock object
     *
     * @var \PHPUnit_Framework_MockObject_MockObject $_matomoBlockMock
     */
    protected $_matomoBlockMock;

    /**
     * Search result block mock object
     *
     * @var \PHPUnit_Framework_MockObject_MockObject $_searchResultBlockMock
     */
    protected $_searchResultBlockMock;

    /**
     * Event observer mock object
     *
     * @var \PHPUnit_Framework_MockObject_MockObject $_eventObserverMock
     */
    protected $_eventObserverMock;

    /**
     * Set up
     *
     * @return void
     */
    public function setUp(): void
    {
        $className = \Chessio\Matomo\Observer\SearchResultObserver::class;
        $objectManager = new ObjectManager($this);
        $arguments = $objectManager->getConstructArguments($className);

        $this->_trackerMock = $this->createPartialMock(
            \Chessio\Matomo\Model\Tracker::class,
            ['trackSiteSearch']
        );
        $arguments['matomoTracker'] = $this->_trackerMock;
        $this->_dataHelperMock = $arguments['dataHelper'];

        $this->_layoutMock = $this->createMock(
            \Magento\Framework\View\Layout::class
        );
        $arguments['view']
            ->expects($this->any())
            ->method('getLayout')
            ->willReturn($this->_layoutMock);

        $this->_queryMock = $this->createPartialMock(
            \Magento\Search\Model\Query::class,
            ['getQueryText', 'getNumResults']
        );
        $arguments['queryFactory']
            ->expects($this->any())
            ->method('get')
            ->willReturn($this->_queryMock);

        $this->_observer = $objectManager->getObject($className, $arguments);
        $this->_matomoBlockMock = $this->createPartialMock(
            \Chessio\Matomo\Block\Matomo::class,
            ['setSkipTrackPageView']
        );
        $this->_searchResultBlockMock = $this->createMock(
            \Magento\CatalogSearch\Block\Result::class
        );
        $this->_eventObserverMock = $this->createMock(
            \Magento\Framework\Event\Observer::class
        );
    }

    /**
     * Prepare the search query mock object with given text and result count
     *
     * @param string $queryText
     * @param int|null $numResults
     */
    protected function _prepareQueryMock($queryText, $numResults)
    {
        $this->_queryMock
            ->expects($this->once())
            ->method('getQueryText')
            ->willReturn($queryText);
        $this->_queryMock
            ->expects($this->once())
            ->method('getNumResults')
            ->willReturn($numResults);
    }

    /**
     * Prepare layout mock object with given blocks
     *
     * @param array $blocks
     */
    protected function _prepareLayoutMock($blocks = [])
    {
        $blockMap = [['matomo.tracker', $this->_matomoBlockMock]];
        foreach ($blocks as $name => $block) {
            $blockMap[] = [$name, $block];
        }
        $this->_layoutMock
            ->expects($this->any())
            ->method('getBlock')
            ->willReturnMap($blockMap);
        $this->_matomoBlockMock
            ->expects($this->once())
            ->method('setSkipTrackPageView')
            ->with(true)
            ->willReturn($this->_matomoBlockMock);
    }

    /**
     * Test for \Chessio\Matomo\Observer\SearchResultObserver::execute where
     * the query object does not have a result count.
     *
     * @return void
     */
    public function testExecuteWithNewQuery()
    {
        $queryText = 'Some query text';
        $resultsCount = 5;

        // Enable tracking
        $this->_dataHelperMock
            ->expects($this->once())
            ->method('isTrackingEnabled')
            ->willReturn(true);

        $this->_prepareQueryMock($queryText, null);
        $this->_prepareLayoutMock([
            'search.result' => $this->_searchResultBlockMock
        ]);

        // Make sure the search result block is called to access a result count
        $this->_searchResultBlockMock
            ->expects($this->once())
            ->method('getResultCount')
            ->willReturn($resultsCount);

        // Make sure the trackers' `trackSiteSearch' is called exactly once
        $this->_trackerMock
            ->expects($this->once())
            ->method('trackSiteSearch')
            ->with($queryText, false, $resultsCount)
            ->willReturn($this->_trackerMock);

        // Assert that `execute' returns $this
        $this->assertSame(
            $this->_observer,
            $this->_observer->execute($this->_eventObserverMock)
        );
    }

    /**
     * Test for \Chessio\Matomo\Observer\SearchResultObserver::execute where
     * the query object does not have a result count and there is no search
     * result block available.
     *
     * @return void
     */
    public function testExecuteWithNewQueryAndNoResultBlock()
    {
        $queryText = 'Some query text';

        // Enable tracking
        $this->_dataHelperMock
            ->expects($this->once())
            ->method('isTrackingEnabled')
            ->willReturn(true);

        $this->_prepareQueryMock($queryText, null);
        $this->_prepareLayoutMock(['search.result' => false]);

        // Make sure the trackers' `trackSiteSearch' is called exactly once
        $this->_trackerMock
            ->expects($this->once())
            ->method('trackSiteSearch')
            ->with($queryText) // No results count available
            ->willReturn($this->_trackerMock);

        // Assert that `execute' returns $this
        $this->assertSame(
            $this->_observer,
            $this->_observer->execute($this->_eventObserverMock)
        );
    }

    /**
     * Test for \Chessio\Matomo\Observer\SearchResultObserver::execute where
     * the query object has a result count.
     *
     * @return void
     */
    public function testExecuteWithExistingQuery()
    {
        $queryText = 'Some query text';
        $resultsCount = 5;

        // Enable tracking
        $this->_dataHelperMock
            ->expects($this->once())
            ->method('isTrackingEnabled')
            ->willReturn(true);

        $this->_prepareQueryMock($queryText, $resultsCount);
        $this->_prepareLayoutMock([
            'search.result' => $this->_searchResultBlockMock
        ]);

        // Make sure the search result block is not accessed when the query
        // itself already has a result count.
        $this->_searchResultBlockMock
            ->expects($this->never())
            ->method('getResultCount');

        // Make sure the trackers' `trackSiteSearch' is called exactly once
        $this->_trackerMock
            ->expects($this->once())
            ->method('trackSiteSearch')
            ->with($queryText, false, $resultsCount)
            ->willReturn($this->_trackerMock);

        // Assert that `execute' returns $this
        $this->assertSame(
            $this->_observer,
            $this->_observer->execute($this->_eventObserverMock)
        );
    }

    /**
     * Test for \Chessio\Matomo\Observer\SearchResultObserver::execute where
     * tracking is disabled.
     *
     * @return void
     */
    public function testExecuteWithTrackingDisabled()
    {
        // Disable tracking
        $this->_dataHelperMock
            ->expects($this->once())
            ->method('isTrackingEnabled')
            ->willReturn(false);

        // Make sure the trackers' `trackSiteSearch' is never called
        $this->_trackerMock
            ->expects($this->never())
            ->method('trackSiteSearch');

        // Assert that `execute' returns $this
        $this->assertSame(
            $this->_observer,
            $this->_observer->execute($this->_eventObserverMock)
        );
    }
}
