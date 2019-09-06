/**
 * Need to map these to file-uploader2 as it's used for downloadable products
 */
var config = {
    map: {
        '*': {
        	"Magento_Downloadable/js/components/file-uploader":"Ced_CsProduct/js/components/file-uploader2",
	        "Magento_Downloadable/template/components/file-uploader.html":"Ced_CsProduct/template/components/file-uploader2.html"
        }
    }
    
};
