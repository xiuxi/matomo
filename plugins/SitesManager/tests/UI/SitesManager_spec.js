/*!
 * Matomo - free/libre analytics platform
 *
 * SitesManager screenshot tests.
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

describe("SitesManager", function () {
    this.timeout(0);
    this.fixture = "Piwik\\Plugins\\SitesManager\\tests\\Fixtures\\ManySites";

    var url = "?module=SitesManager&action=index&idSite=1&period=day&date=yesterday&showaddsite=false";

    async function assertScreenshotEquals(screenshotName, test)
    {
        await test();
        pageWrap = await page.$('#content');
        expect(await pageWrap.screenshot()).to.matchImage(screenshotName);
    }

    async function loadNextPage(page)
    {
        await (await page.jQuery('.SitesManager .paging:first .next')).click();
    }

    async function loadPreviousPage(page)
    {
        await (await page.jQuery('.SitesManager .paging:first .prev')).click();
    }

    async function searchForText(page, textToAppendToSearchField)
    {
        await (await page.jQuery('.SitesManager .search:first input')).type(textToAppendToSearchField);
        await (await page.jQuery('.SitesManager .search:first img')).click();
    }

    it("should load correctly and show page 0", async function() {
        assertScreenshotEquals("loaded", async function () {
            await page.goto(url);
        });
    });

    it("should show page 1 when clicking next", async function() {
        assertScreenshotEquals("page_1", async function () {
            await loadNextPage(page);
        });
    });

    it("should show page 2 when clicking next", async function() {
        assertScreenshotEquals("page_2", async function () {
            await loadNextPage(page);
        });
    });

    it("should show page 1 when clicking prev", async function() {
        assertScreenshotEquals("page_1_again", async function () {
            await loadPreviousPage(page);
        });
    });

    it("should search for websites and reset page to 0", async function() {
        assertScreenshotEquals("search", async function () {
            await searchForText(page, 'SiteTes');
        });
    });

    it("should page within search result to page 1", async function() {
        assertScreenshotEquals("search_page_1", async function () {
            await loadNextPage(page);
        });
    });

    it("should search for websites no result", async function() {
        assertScreenshotEquals("search_no_result", async function () {
            await searchForText(page, 'RanDoMSearChTerm');
        });
    });

    it("should load the global settings page", async function() {
        assertScreenshotEquals("global_settings", async function () {
            await page.goto('?module=SitesManager&action=globalSettings&idSite=1&period=day&date=yesterday&showaddsite=false');
            await page.evaluate(function () {
                $('.form-help:contains(UTC time is)').hide();
            });
        });
    });
});