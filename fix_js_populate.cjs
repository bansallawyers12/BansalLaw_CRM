const fs = require('fs');
let content = fs.readFileSync('public/js/crm/clients/detail-main.js', 'utf8');

// Find the populate block by unique anchor strings
const blockStart = content.indexOf('if(obj.cost_assignment_matterInfo){');
const anchorAfterSurcharge = content.lastIndexOf('#TotalDoHASurcharges');
// The block ends at the closing } of the else branch
const blockEnd = content.indexOf('\n                        }', anchorAfterSurcharge) + '\n                        }'.length;

if (blockStart === -1 || anchorAfterSurcharge === -1) {
    console.error('Anchor not found. blockStart=' + blockStart + ' anchor=' + anchorAfterSurcharge);
    process.exit(1);
}

console.log('blockStart=' + blockStart + ', blockEnd=' + blockEnd);

const replacement = [
    "if(obj.cost_assignment_matterInfo){",
    "",
    "                            $scope.find('#Block_1_Ex_Tax').val(obj.cost_assignment_matterInfo.Block_1_Ex_Tax);",
    "                            $scope.find('#Block_2_Ex_Tax').val(obj.cost_assignment_matterInfo.Block_2_Ex_Tax);",
    "                            $scope.find('#Block_3_Ex_Tax').val(obj.cost_assignment_matterInfo.Block_3_Ex_Tax);",
    "                            $scope.find('#additional_fee_1').val(obj.cost_assignment_matterInfo.additional_fee_1);",
    "                            $scope.find('#TotalBLOCKFEE').val(obj.cost_assignment_matterInfo.TotalBLOCKFEE);",
    "",
    "                            // Populate disbursement lines",
    "                            var lines = obj.cost_assignment_matterInfo.disbursement_lines || [];",
    "                            populateDisbursementRows(lines, $scope.find('#disbursement-rows'));",
    "                            calculateTotalDisbursements(modalContainer);",
    "",
    "                        } else {",
    "",
    "                            $scope.find('#Block_1_Ex_Tax').val(obj.matterInfo.Block_1_Ex_Tax);",
    "                            $scope.find('#Block_2_Ex_Tax').val(obj.matterInfo.Block_2_Ex_Tax);",
    "                            $scope.find('#Block_3_Ex_Tax').val(obj.matterInfo.Block_3_Ex_Tax);",
    "                            $scope.find('#additional_fee_1').val(obj.matterInfo.additional_fee_1);",
    "                            $scope.find('#TotalBLOCKFEE').val(obj.matterInfo.TotalBLOCKFEE);",
    "                            populateDisbursementRows([], $scope.find('#disbursement-rows'));",
    "",
    "                        }",
].join('\n');

const newContent = content.substring(0, blockStart) + replacement + content.substring(blockEnd);
fs.writeFileSync('public/js/crm/clients/detail-main.js', newContent, 'utf8');
console.log('Done. Old block length: ' + (blockEnd - blockStart) + ', new length: ' + replacement.length);
