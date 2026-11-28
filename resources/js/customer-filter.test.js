import test from 'node:test';
import assert from 'node:assert/strict';
import { filterCustomerOptions, findCustomerOption, syncCustomerFilterLabel } from './customer-filter.js';
const options=[{value:'',textContent:'सभी ग्राहक'},{value:'12',textContent:'रामलाल · 9876543210',dataset:{search:'9876543210 C-012 12'}},{value:'13',textContent:'श्यामलाल · #13',dataset:{search:'C-013 13'}}];
test('matches partial customer name, mobile, code and id',()=>{assert.deepEqual(filterCustomerOptions(options,'राम'),[options[1]]);assert.deepEqual(filterCustomerOptions(options,'987654'),[options[1]]);assert.deepEqual(filterCustomerOptions(options,'C-013'),[options[2]]);assert.deepEqual(filterCustomerOptions(options,'13'),[options[2]]);});
test('empty query excludes all-customers placeholder',()=>assert.deepEqual(filterCustomerOptions(options,''),options.slice(1)));
test('keyboard exact matching does not select partial labels',()=>{assert.equal(findCustomerOption(options,'रामलाल · 9876543210')?.value,'12');assert.equal(findCustomerOption(options,'राम'),null);});
test('visible label follows selected customer id',()=>{const input={value:''};const select={value:'12',options};syncCustomerFilterLabel(input,select);assert.equal(input.value,'रामलाल · 9876543210');select.value='';syncCustomerFilterLabel(input,select);assert.equal(input.value,'');});
