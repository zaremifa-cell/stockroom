<script setup>
import { computed, ref } from 'vue';
import { euros } from '../api';

const props = defineProps({ warehouses: Array, products: Array });
const query = ref('');
const lowOnly = ref(false);

const isLow = (product, warehouseId) => (product.stock[warehouseId] ?? 0) < product.low_stock_threshold;

const rows = computed(() => {
    const q = query.value.trim().toLowerCase();
    return props.products.filter((p) => {
        if (q && !`${p.sku} ${p.name}`.toLowerCase().includes(q)) return false;
        return !lowOnly.value || props.warehouses.some((w) => isLow(p, w.id));
    });
});

const lowCount = computed(() => props.products.reduce((n, p) => n + props.warehouses.filter((w) => isLow(p, w.id)).length, 0));
const units = computed(() => props.products.reduce((n, p) => n + p.total, 0));
const value = computed(() => props.products.reduce((n, p) => n + p.total * p.price_cents, 0));
</script>

<template>
    <div class="stack">
        <section class="tiles">
            <div class="tile"><span>Products</span><strong>{{ products.length }}</strong></div>
            <div class="tile"><span>Units in stock</span><strong>{{ units.toLocaleString('en-GB') }}</strong></div>
            <div class="tile"><span>Stock value</span><strong>{{ euros(value) }}</strong></div>
            <div class="tile" :class="{ warn: lowCount > 0 }"><span>Running low</span><strong>{{ lowCount }}</strong></div>
        </section>

        <section class="card">
            <div class="card-head">
                <h2>Stock by warehouse</h2>
                <div class="row">
                    <input v-model="query" type="search" placeholder="Search SKU or name" aria-label="Search products" />
                    <label class="check"><input v-model="lowOnly" type="checkbox" /> Low stock only</label>
                </div>
            </div>
            <ul class="stock-cards" aria-label="Stock by warehouse">
                <li v-for="p in rows" :key="p.id" class="stock-card">
                    <div class="stock-card-head">
                        <div><strong>{{ p.name }}</strong><div class="muted small"><code>{{ p.sku }}</code> · {{ euros(p.price_cents) }}</div></div>
                        <span class="stock-total">{{ p.total }}</span>
                    </div>
                    <dl class="stock-grid">
                        <div v-for="w in warehouses" :key="w.id" :class="{ low: isLow(p, w.id) }">
                            <dt class="muted small">{{ w.name }}</dt>
                            <dd>{{ p.stock[w.id] ?? 0 }}<span v-if="isLow(p, w.id)" class="sr"> (low)</span></dd>
                        </div>
                    </dl>
                </li>
                <li v-if="rows.length === 0" class="muted">No products match.</li>
            </ul>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="num">Price</th>
                            <th v-for="w in warehouses" :key="w.id" class="num">{{ w.name }}</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in rows" :key="p.id">
                            <td><strong>{{ p.name }}</strong><div class="muted small"><code>{{ p.sku }}</code></div></td>
                            <td class="num">{{ euros(p.price_cents) }}</td>
                            <td v-for="w in warehouses" :key="w.id" class="num" :class="{ low: isLow(p, w.id) }">
                                {{ p.stock[w.id] ?? 0 }}<span v-if="isLow(p, w.id)" class="sr"> (low)</span>
                            </td>
                            <td class="num">{{ p.total }}</td>
                        </tr>
                        <tr v-if="rows.length === 0"><td :colspan="warehouses.length + 3" class="muted">No products match.</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="muted small">Marked in orange: below the product's low-stock threshold in that warehouse.</p>
        </section>
    </div>
</template>
