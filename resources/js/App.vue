<script setup>
import { onMounted, ref } from 'vue';
import { api } from './api';
import StockTable from './components/StockTable.vue';
import OrderForm from './components/OrderForm.vue';
import TransferForm from './components/TransferForm.vue';
import PriceImport from './components/PriceImport.vue';
import History from './components/History.vue';

const tabs = [
    ['stock', 'Stock'],
    ['order', 'New order'],
    ['transfer', 'Transfer'],
    ['prices', 'Price import'],
    ['history', 'History'],
];
const tab = ref('stock');
const warehouses = ref([]);
const products = ref([]);
const loadError = ref(null);

// One source of truth for stock: every form refreshes it after a successful change.
async function refresh() {
    try {
        [warehouses.value, products.value] = await Promise.all([api.warehouses(), api.products()]);
        loadError.value = null;
    } catch (messages) {
        loadError.value = messages.join(' ');
    }
}

onMounted(refresh);
</script>

<template>
    <div class="shell">
        <header class="top">
            <span class="brand">Stockroom <span class="muted">inventory</span></span>
            <nav class="tabs" aria-label="Sections">
                <button v-for="[id, label] in tabs" :key="id" :class="{ active: tab === id }" :aria-current="tab === id ? 'page' : undefined" @click="tab = id">
                    {{ label }}
                </button>
            </nav>
        </header>

        <p v-if="loadError" class="alert" role="alert">{{ loadError }}</p>

        <main>
            <StockTable v-if="tab === 'stock'" :warehouses="warehouses" :products="products" />
            <OrderForm v-else-if="tab === 'order'" :warehouses="warehouses" :products="products" @changed="refresh" />
            <TransferForm v-else-if="tab === 'transfer'" :warehouses="warehouses" :products="products" @changed="refresh" />
            <PriceImport v-else-if="tab === 'prices'" @changed="refresh" />
            <History v-else />
        </main>
    </div>
</template>
