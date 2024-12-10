<template>
    <Head title="Dashboard"/>

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" enctype="multipart/form-data">
                            <label
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
                                for="file_input"
                            >
                                Upload file
                            </label>
                            <input
                                ref="fileInput"
                                class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400"
                                id="file_input"
                                type="file"
                                accept=".xlsx"
                            >
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                                Please upload an Excel file (.xlsx).
                            </p>

                            <PrimaryButton
                                class="mt-4"
                                :class="{ 'opacity-25': isProcessing.value }"
                                :disabled="isProcessing.value"
                            >
                                Upload file
                            </PrimaryButton>
                        </form>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 mt-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex justify-between items-center mt-4">
                            <button
                                v-if="meta.prev_page_url"
                                @click="changePage(currentPage - 1)"
                                class="px-4 py-2 bg-gray-200 rounded dark:bg-gray-600"
                            >
                                Previous
                            </button>
                            <button
                                v-for="page in meta.last_page"
                                :key="page"
                                :class="['px-4 py-2 rounded', { 'bg-blue-500 text-white': page === currentPage, 'bg-gray-200 dark:bg-gray-600': page !== currentPage }]"
                                @click="changePage(page)"
                            >
                                {{ page }}
                            </button>
                            <button
                                v-if="meta.next_page_url"
                                @click="changePage(currentPage + 1)"
                                class="px-4 py-2 bg-gray-200 rounded dark:bg-gray-600"
                            >
                                Next
                            </button>
                        </div>

                        <table class="table-auto w-full">
                            <thead>
                            <tr>
                                <th class="px-4 py-2">ID</th>
                                <th class="px-4 py-2">Name</th>
                                <th class="px-4 py-2">Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="row in rows" :key="row.id">
                                <td class="border px-4 py-2">{{ row.external_id }}</td>
                                <td class="border px-4 py-2">{{ row.name }}</td>
                                <td class="border px-4 py-2">{{ row.date }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { ref } from 'vue';
import axios from 'axios';

const rows = ref([]);
const meta = ref({});
const currentPage = ref(1);

const fetchRow = async (page = 1) => {
    const response = await axios.get(`/row?page=${page}`);
    rows.value = response.data.data;
    meta.value = response.data;
    currentPage.value = meta.value.current_page;
};

const changePage = (page) => {
    fetchRow(page);
};

fetchRow();

const fileInput = ref(null);
const isProcessing = ref(false);

const submit = async () => {
    if (!fileInput.value.files[0]) {
        return alert("Please select a file to upload.");
    }

    const formData = new FormData();
    formData.append('file', fileInput.value.files[0]);

    try {
        await axios.post(route('upload'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        alert('File uploaded successfully!');
        await fetchRow();
    } catch (error) {
        alert('Failed to upload file.');
    }
};

</script>
