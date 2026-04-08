<template>
  <div>
    <Head title="Blocklist" />
    <h1 id="primary-heading" class="sr-only">Blocklist</h1>

    <div class="sm:flex sm:items-center mb-6">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-grey-900 dark:text-white">Blocklist</h1>
        <p class="mt-2 text-sm text-grey-700 dark:text-grey-200">
          Blocked senders and domains
          {{ search ? 'found for your search' : '- these entries cannot reach your aliases' }}
        </p>
      </div>
    </div>

    <div class="mb-6 p-4 bg-white dark:bg-grey-900 rounded-lg shadow">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-medium text-grey-900 dark:text-white">Add to blocklist</h2>
        <button
          type="button"
          class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
          @click="bulkAddModalOpen = true"
        >
          Bulk add
        </button>
      </div>
      <form class="flex flex-wrap items-end gap-4" @submit.prevent="submitAddForm">
        <div class="flex-shrink-0">
          <label
            for="blocklist-type"
            class="block text-sm font-medium text-grey-700 dark:text-grey-200 mb-1"
          >
            Type
          </label>
          <select
            id="blocklist-type"
            v-model="addForm.type"
            class="rounded-md border-grey-300 dark:border-grey-600 dark:bg-white/5 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          >
            <option value="email" class="dark:bg-grey-900">Email</option>
            <option value="domain" class="dark:bg-grey-900">Domain</option>
          </select>
          <div class="mt-1 min-h-[1.25rem]">
            <p v-if="addForm.errors.type" class="text-sm text-red-500">{{ addForm.errors.type }}</p>
          </div>
        </div>
        <div class="min-w-[200px] flex-1">
          <label
            for="blocklist-value"
            class="block text-sm font-medium text-grey-700 dark:text-grey-200 mb-1"
          >
            {{ addForm.type === 'email' ? 'Email address' : 'Domain' }}
          </label>
          <input
            id="blocklist-value"
            v-model="addForm.value"
            type="text"
            :placeholder="addForm.type === 'email' ? 'e.g. sender@example.com' : 'e.g. example.com'"
            class="block w-full rounded-md border-grey-300 dark:border-grey-600 dark:bg-white/5 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          />
          <div class="mt-1 min-h-[1.25rem]">
            <p v-if="addForm.errors.value" class="text-sm text-red-500">
              {{ addForm.errors.value }}
            </p>
          </div>
        </div>
        <div class="flex flex-col">
          <span class="block text-sm font-medium mb-1 invisible" aria-hidden="true">Add</span>
          <button
            type="submit"
            class="bg-cyan-400 hover:bg-cyan-300 text-cyan-900 font-bold py-2 px-3 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed"
            :disabled="addFormLoading"
          >
            Add to blocklist
            <Loader v-if="addFormLoading" />
          </button>
          <div class="mt-1 min-h-[1.25rem]"></div>
        </div>
      </form>
    </div>

    <div v-if="rows.length" class="relative">
      <div
        v-if="selectedRows.length > 0"
        id="bulk-actions"
        class="horizontal-scroll absolute px-0.5 top-0 left-12 flex flex-nowrap w-full h-12 items-center space-x-3 bg-gradient-to-r from-white dark:from-grey-900 z-10 overflow-x-auto"
        style="width: calc(100% - 3rem)"
      >
        <button
          type="button"
          class="ml-1 inline-flex items-center rounded border border-grey-300 bg-white px-2.5 py-1.5 text-xs font-medium text-grey-700 shadow-sm hover:bg-grey-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-30 dark:border-grey-600 dark:bg-grey-800 dark:text-grey-200 dark:hover:bg-grey-700"
          :disabled="bulkDeleteLoading"
          @click="
            selectedRows.length === 1
              ? openDeleteModal(selectedRows[0].id)
              : (bulkDeleteModalOpen = true)
          "
        >
          Delete
          <Loader v-if="bulkDeleteLoading" />
        </button>
        <span class="font-semibold text-indigo-800 hidden md:inline-block dark:text-indigo-400">
          {{ selectedRows.length === 1 ? '1 entry' : `${selectedRows.length} entries` }}
        </span>
      </div>
      <vue-good-table
        v-on:sort-change="debounceTooltips"
        :columns="columns"
        :rows="rows"
        :sort-options="{
          enabled: true,
          initialSortBy: { field: 'created_at', type: 'desc' },
        }"
        styleClass="vgt-table"
        :row-style-class="rowStyleClassFn"
      >
        <template #table-column="props">
          <span v-if="props.column.field === 'select'">
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-grey-300 text-indigo-600 focus:ring-indigo-500 dark:text-indigo-400 dark:bg-grey-950"
              :checked="indeterminate || selectedRowIds.length === rows.length"
              :indeterminate="indeterminate"
              @change="selectedRowIds = $event.target.checked ? rows.map(r => r.id) : []"
            />
          </span>
          <span
            v-else-if="props.column.field === 'blocked'"
            :class="selectedRows.length > 0 ? 'blur-sm' : ''"
          >
            {{ props.column.label }}
            <span
              class="tooltip outline-none"
              data-tippy-content="This is the number of times this entry has blocked an email. Hover over the count to see when it last blocked."
            >
              <icon name="info" class="inline-block w-4 h-4 text-grey-300 fill-current" />
            </span>
          </span>
          <span v-else :class="selectedRows.length > 0 ? 'blur-sm' : ''">
            {{ props.column.label }}
          </span>
        </template>
        <template #table-row="props">
          <span v-if="props.column.field === 'select'" class="flex items-center">
            <div
              v-if="selectedRowIds.includes(props.row.id)"
              class="absolute inset-y-0 left-0 w-0.5 bg-indigo-600"
            ></div>
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-grey-300 text-indigo-600 focus:ring-indigo-500 dark:text-indigo-400 dark:bg-grey-950"
              :value="props.row.id"
              v-model="selectedRowIds"
            />
          </span>
          <span
            v-else-if="props.column.field === 'created_at'"
            class="tooltip outline-none cursor-default text-sm text-grey-500 dark:text-grey-300"
            :data-tippy-content="$filters.formatDate(rows[props.row.originalIndex].created_at)"
          >
            {{ $filters.timeAgo(props.row.created_at) }}
          </span>
          <span
            v-else-if="props.column.field === 'value'"
            class="tooltip cursor-pointer outline-none text-sm font-medium text-grey-700 dark:text-grey-200"
            data-tippy-content="Click to copy"
            @click="clipboard(rows[props.row.originalIndex].value)"
          >
            {{ props.row.value }}
          </span>
          <span
            v-else-if="props.column.field === 'type'"
            class="text-sm text-grey-500 dark:text-grey-300"
          >
            {{ props.row.type === 'email' ? 'Email' : 'Domain' }}
          </span>
          <span v-else-if="props.column.field === 'blocked'">
            <span
              v-if="rows[props.row.originalIndex].last_blocked"
              class="tooltip outline-none cursor-default font-semibold text-indigo-800 dark:text-indigo-400"
              :data-tippy-content="
                $filters.timeAgo(rows[props.row.originalIndex].last_blocked) +
                ' (' +
                $filters.formatDate(rows[props.row.originalIndex].last_blocked) +
                ')'
              "
              >{{ props.row.blocked.toLocaleString() }}
            </span>
            <span v-else class="dark:text-grey-300">{{ props.row.blocked.toLocaleString() }} </span>
          </span>
          <span v-else class="flex items-center justify-center outline-none" tabindex="-1">
            <button
              type="button"
              class="text-indigo-500 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-500 font-medium"
              @click="openDeleteModal(props.row.id)"
            >
              Delete
            </button>
          </span>
        </template>
      </vue-good-table>
    </div>

    <div v-else-if="search" class="text-center py-12">
      <NoSymbolIcon class="mx-auto h-16 w-16 text-grey-400 dark:text-grey-200" />
      <h3 class="mt-2 text-lg font-medium text-grey-900 dark:text-white">
        No blocklist entries found for that search
      </h3>
      <p class="mt-1 text-md text-grey-500 dark:text-grey-200">
        Try entering a different search term.
      </p>
      <div class="mt-6">
        <Link
          :href="route('blocklist.index')"
          class="inline-flex items-center rounded-md border border-transparent bg-cyan-400 hover:bg-cyan-300 text-cyan-900 px-4 py-2 text-sm font-medium shadow-sm focus:outline-none"
        >
          View all blocklist entries
        </Link>
      </div>
    </div>

    <div v-else class="text-center py-12">
      <NoSymbolIcon class="mx-auto h-16 w-16 text-grey-400 dark:text-grey-200" />
      <h3 class="mt-2 text-lg font-medium text-grey-900 dark:text-white">No blocklist entries</h3>
      <p class="mt-1 text-md text-grey-500 dark:text-grey-200">
        Add an email address or domain above to block it from reaching your aliases.
      </p>
    </div>

    <Modal :open="deleteModalOpen" @close="closeDeleteModal">
      <template v-slot:title>Remove from blocklist</template>
      <template v-slot:content>
        <p class="mt-4 text-grey-700 dark:text-grey-200">
          Are you sure you want to remove this entry from your blocklist? The sender or domain will
          be able to reach your aliases again.
        </p>
        <div class="mt-6 flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
          <button
            type="button"
            class="px-4 py-3 text-white font-semibold bg-red-500 hover:bg-red-600 border border-transparent rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed"
            :disabled="deleteLoading"
            @click="confirmDelete"
          >
            Remove from blocklist
            <Loader v-if="deleteLoading" class="inline-block ml-2 h-4 w-4" />
          </button>
          <button
            class="px-4 py-3 text-grey-800 font-semibold bg-white hover:bg-grey-50 dark:text-grey-100 dark:hover:bg-grey-700 dark:bg-grey-600 dark:border-grey-700 border border-grey-100 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
            @click="closeDeleteModal"
          >
            Cancel
          </button>
        </div>
      </template>
    </Modal>

    <Modal :open="bulkDeleteModalOpen" @close="bulkDeleteModalOpen = false">
      <template v-slot:title>Remove from blocklist</template>
      <template v-slot:content>
        <p class="mt-4 text-grey-700 dark:text-grey-200">
          Are you sure you want to remove these <b>{{ selectedRows.length }}</b> entries from your
          blocklist? The senders or domains will be able to reach your aliases again.
        </p>
        <div class="mt-6 flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
          <button
            type="button"
            class="px-4 py-3 text-white font-semibold bg-red-500 hover:bg-red-600 border border-transparent rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed"
            :disabled="bulkDeleteLoading"
            @click="bulkDeleteBlocklist"
          >
            Remove from blocklist
            <Loader v-if="bulkDeleteLoading" class="inline-block ml-2 h-4 w-4" />
          </button>
          <button
            class="px-4 py-3 text-grey-800 font-semibold bg-white hover:bg-grey-50 dark:text-grey-100 dark:hover:bg-grey-700 dark:bg-grey-600 dark:border-grey-700 border border-grey-100 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
            @click="bulkDeleteModalOpen = false"
          >
            Cancel
          </button>
        </div>
      </template>
    </Modal>

    <Modal :open="bulkAddModalOpen" @close="closeBulkAddModal">
      <template v-slot:title>Bulk add to blocklist</template>
      <template v-slot:content>
        <p class="mt-2 text-sm text-grey-600 dark:text-grey-300">
          Enter one {{ bulkAddType === 'email' ? 'email address' : 'domain' }} per line. Duplicates
          and entries already on your blocklist will be skipped. Maximum 50 entries.
        </p>
        <div class="mt-4">
          <label
            for="bulk-add-type"
            class="block text-sm font-medium text-grey-700 dark:text-grey-200 mb-1"
          >
            Type
          </label>
          <select
            id="bulk-add-type"
            v-model="bulkAddType"
            class="rounded-md border-grey-300 dark:border-grey-600 dark:bg-white/5 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          >
            <option value="email" class="dark:bg-grey-900">Email</option>
            <option value="domain" class="dark:bg-grey-900">Domain</option>
          </select>
        </div>
        <div class="mt-4">
          <label
            for="bulk-add-values"
            class="block text-sm font-medium text-grey-700 dark:text-grey-200 mb-1"
          >
            Entries
          </label>
          <textarea
            id="bulk-add-values"
            v-model="bulkAddText"
            rows="8"
            class="block w-full rounded-md border-grey-300 dark:border-grey-600 dark:bg-white/5 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
            :placeholder="
              bulkAddType === 'email'
                ? 'spam@example.com\nnewsletter@company.com'
                : 'example.com\nspammer.org'
            "
          />
          <p v-if="bulkAddError" class="mt-1 text-sm text-red-500">
            {{ bulkAddError }}
          </p>
        </div>
        <div class="mt-6 flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
          <button
            type="button"
            class="bg-cyan-400 hover:bg-cyan-300 text-cyan-900 font-bold py-3 px-4 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed"
            :disabled="bulkAddLoading || !parsedBulkAddValues.length"
            @click="submitBulkAdd"
          >
            Add to blocklist
            <Loader v-if="bulkAddLoading" class="inline-block ml-2 h-4 w-4" />
          </button>
          <button
            type="button"
            class="px-4 py-3 text-grey-800 font-semibold bg-white hover:bg-grey-50 dark:text-grey-100 dark:hover:bg-grey-700 dark:bg-grey-600 dark:border-grey-700 border border-grey-100 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
            @click="closeBulkAddModal"
          >
            Cancel
          </button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import Modal from '../../Components/Modal.vue'
import Loader from '../../Components/Loader.vue'
import { VueGoodTable } from 'vue-good-table-next'
import { notify } from '@kyvg/vue3-notification'
import { NoSymbolIcon } from '@heroicons/vue/24/outline'
import { roundArrow } from 'tippy.js'
import tippy from 'tippy.js'

const props = defineProps({
  initialRows: {
    type: Array,
    required: true,
  },
  search: {
    type: String,
    default: null,
  },
})

onMounted(() => {
  addTooltips()
})

const rows = ref([...props.initialRows])
const selectedRowIds = ref([])
const selectedRows = computed(() => rows.value.filter(row => selectedRowIds.value.includes(row.id)))
const indeterminate = computed(
  () => selectedRowIds.value.length > 0 && selectedRowIds.value.length < rows.value.length,
)
const deleteModalOpen = ref(false)
const deleteLoading = ref(false)
const bulkDeleteModalOpen = ref(false)
const bulkDeleteLoading = ref(false)
const bulkAddModalOpen = ref(false)
const bulkAddLoading = ref(false)
const bulkAddType = ref('email')
const bulkAddText = ref('')
const bulkAddError = ref(null)
const addFormLoading = ref(false)
const idToDelete = ref(null)
const tippyInstance = ref(null)

const parsedBulkAddValues = computed(() => {
  if (!bulkAddText.value) return []
  return bulkAddText.value
    .split(/\r?\n/)
    .map(line => line.trim())
    .filter(Boolean)
    .slice(0, 50)
})

const rowStyleClassFn = row =>
  selectedRowIds.value.includes(row.id) ? 'bg-grey-50 dark:bg-grey-950' : ''

const addForm = useForm({
  type: 'email',
  value: '',
})

const submitAddForm = () => {
  addForm.clearErrors()
  addFormLoading.value = true
  axios
    .post(
      '/api/v1/blocklist',
      { type: addForm.type, value: addForm.value },
      { withCredentials: true },
    )
    .then(({ data }) => {
      rows.value.push(data.data)
      addForm.reset()
      debounceTooltips()
      successMessage('New entry added')
    })
    .catch(err => {
      if (err.response?.status === 422 && err.response?.data?.errors) {
        for (const [key, messages] of Object.entries(err.response.data.errors)) {
          addForm.setError(key, Array.isArray(messages) ? messages[0] : messages)
        }
      }
    })
    .finally(() => {
      addFormLoading.value = false
    })
}

const columns = [
  {
    label: '',
    field: 'select',
    sortable: false,
    globalSearchDisabled: true,
  },
  {
    label: 'Value',
    field: 'value',
  },
  {
    label: 'Type',
    field: 'type',
  },
  {
    label: 'Blocked',
    field: 'blocked',
    type: 'number',
    sortable: false,
  },
  {
    label: 'Created',
    field: 'created_at',
    globalSearchDisabled: true,
  },
  {
    label: '',
    field: 'actions',
    sortable: false,
    globalSearchDisabled: true,
  },
]

const openDeleteModal = id => {
  idToDelete.value = id
  deleteModalOpen.value = true
}

const closeDeleteModal = () => {
  deleteModalOpen.value = false
  idToDelete.value = null
}

const confirmDelete = () => {
  if (!idToDelete.value) return
  deleteLoading.value = true
  axios
    .delete(`/api/v1/blocklist/${idToDelete.value}`, { withCredentials: true })
    .then(() => {
      rows.value = rows.value.filter(row => row.id !== idToDelete.value)
      selectedRowIds.value = selectedRowIds.value.filter(id => id !== idToDelete.value)
      closeDeleteModal()
    })
    .catch(error => {
      errorMessage()
      deleteLoading.value = false
      deleteModalOpen.value = false
    })
    .finally(() => {
      deleteLoading.value = false
    })
}

const bulkDeleteBlocklist = () => {
  bulkDeleteLoading.value = true
  axios
    .post('/api/v1/blocklist/delete/bulk', JSON.stringify({ ids: selectedRowIds.value }), {
      headers: { 'Content-Type': 'application/json' },
      withCredentials: true,
    })
    .then(response => {
      rows.value = rows.value.filter(row => !selectedRowIds.value.includes(row.id))
      selectedRowIds.value = []
      bulkDeleteModalOpen.value = false
      successMessage(response.data.message)
    })
    .catch(error => {
      bulkDeleteLoading.value = false
      if (error.response?.status === 404) {
        errorMessage(error.response.data?.message ?? 'No blocklist entries found')
      } else if (error.response?.status === 422) {
        errorMessage(
          error.response?.data?.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : 'Validation failed',
        )
      } else {
        errorMessage()
      }
    })
    .finally(() => {
      bulkDeleteLoading.value = false
    })
}

const closeBulkAddModal = () => {
  bulkAddModalOpen.value = false
  bulkAddText.value = ''
  bulkAddError.value = null
}

const submitBulkAdd = () => {
  if (!parsedBulkAddValues.value.length) return
  bulkAddError.value = null
  bulkAddLoading.value = true
  axios
    .post(
      '/api/v1/blocklist/store/bulk',
      JSON.stringify({
        type: bulkAddType.value,
        values: parsedBulkAddValues.value,
      }),
      {
        headers: { 'Content-Type': 'application/json' },
        withCredentials: true,
      },
    )
    .then(response => {
      const created = response.data.data || []
      created.forEach(entry => rows.value.unshift(entry))
      closeBulkAddModal()
      debounceTooltips()
      successMessage(response.data.message)
    })
    .catch(error => {
      if (error.response?.status === 403) {
        bulkAddError.value = error.response.data?.message ?? 'An error occurred. Please try again.'
      } else if (error.response?.status === 422 && error.response?.data?.errors) {
        const errors = error.response.data.errors
        bulkAddError.value = Object.values(errors).flat().filter(Boolean)[0] ?? 'Validation failed.'
      } else {
        bulkAddError.value = 'An error occurred. Please try again.'
      }
    })
    .finally(() => {
      bulkAddLoading.value = false
    })
}

const addTooltips = () => {
  if (tippyInstance.value) {
    tippyInstance.value.forEach(instance => instance.destroy())
  }
  tippyInstance.value = tippy('.tooltip', {
    arrow: roundArrow,
    allowHTML: true,
  })
}

const debounceTooltips =
  typeof _.debounce === 'function' ? _.debounce(addTooltips, 50) : addTooltips

const clipboard = (str, success, error) => {
  // Needed as v-clipboard doesn't work inside modals!
  navigator.clipboard.writeText(str).then(
    () => {
      successMessage('Copied to clipboard')
    },
    () => {
      errorMessage('Could not copy to clipboard')
    },
  )
}

const successMessage = (text = '') => {
  notify({
    title: 'Success',
    text: text,
    type: 'success',
  })
}

const errorMessage = (text = 'An error has occurred, please try again later') => {
  notify({
    title: 'Error',
    text: text,
    type: 'error',
  })
}
</script>
