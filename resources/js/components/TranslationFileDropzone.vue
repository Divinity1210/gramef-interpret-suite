<template>
  <div>
    <ul class="list-unstyled" v-if="documentData !== null">
        <li v-for="doc in documentData" :key="doc.id || (doc.fullUrl || doc.url) || doc.name" class="d-flex align-items-center justify-content-between mb-2">
            <a :href="doc.fullUrl || doc.url">{{ doc.name || doc.file_name || doc.original_name }}</a>
            <button
                v-if="removeUrl && doc.id"
                type="button"
                class="btn btn-outline-danger btn--small ml-2"
                @click="removeExisting(doc)"
            >
                Cancel upload
            </button>
        </li>
    </ul>
    <div class="font-weight-bold mb-2">{{ label }}</div>
    <vue-dropzone
        :id="`translationFileDropzone${documentType}`"
        :options="dropzoneOptions"
        @vdropzone-sending="sending"
        @vdropzone-success="uploaded"
        @vdropzone-error="failed"
    ></vue-dropzone>
  </div>
</template>

<script>
import vue2Dropzone from 'vue2-dropzone'
import 'vue2-dropzone/dist/vue2Dropzone.min.css'

export default {
    components: {
        vueDropzone: vue2Dropzone,
    },
    props: {
        existingDocuments: {
            type: Array,
            required: false,
        },
        documentType: {
            type: Number,
            required: true,
        },
        metadataFields: {
            type: Array,
            required: false,
            default: () => [],
        },
        acceptedFiles: {
            type: String,
            required: false,
            default: null,
        },
        maxFilesize: {
            type: Number,
            required: false,
            default: null,
        },
        label: {
            type: String,
            required: true,
        },
        url: {
            type: String,
            required: true,
        },
        reloadOnSuccess: {
            type: Boolean,
            required: false,
            default: false,
        },
        removeUrl: {
            type: String,
            required: false,
            default: null,
        },
    },
    data() {
        return {
            documentData: this.existingDocuments || [],
            dropzoneOptions: {
                url: this.url,
                headers: {
                    'x-csrf-token' : document.querySelector('meta[name="csrf-token"]').content
                },
                addRemoveLinks: true,
                parallelUploads: 1,
            },
        }
    },
    created() {
        // Apply optional restrictions for file types and size
        if (this.acceptedFiles) {
            this.dropzoneOptions.acceptedFiles = this.acceptedFiles
        }
        if (this.maxFilesize) {
            this.dropzoneOptions.maxFilesize = this.maxFilesize
        }
    },
    methods: {
        sending(file, xhr, formData) {
            formData.append('document_type', this.documentType)
            // Append optional metadata fields if present on the page
            if (Array.isArray(this.metadataFields) && this.metadataFields.length) {
                this.metadataFields.forEach((field) => {
                    const el = document.querySelector(`[name="${field}"]`)
                    if (el && typeof el.value !== 'undefined' && el.value !== '') {
                        formData.append(field, el.value)
                    }
                })
            }
        },
        uploaded(file, response) {
            let payload
            try {
                payload = typeof response === 'string' ? JSON.parse(response) : response
            } catch (e) {
                payload = {}
            }
            const name = payload.name || payload.file_name || payload.original_name || (file && file.name) || ''
            const fullUrl = payload.fullUrl || payload.url || payload.file_path || ''
            const item = { name, fullUrl }
            if (this.documentType == 9) {
                this.documentData = Array.isArray(this.documentData) ? this.documentData.concat([item]) : [item]
            } else {
                this.documentData = [item]
            }
            if (this.reloadOnSuccess) {
                window.location.reload()
            }
        },
        failed(file, message) {
            // Basic error hook: display message
            // In future, this can be replaced with a toast component
            alert(typeof message === 'string' ? message : 'Upload failed. Please check file type/size and try again.')
        },
        async removeExisting(doc) {
            if (!doc || !doc.id || !this.removeUrl) return
            if (!window.confirm('Remove this uploaded timesheet?')) return
            try {
                await axios.delete(this.removeUrl, {
                    data: {
                        document_id: doc.id,
                    },
                    headers: {
                        'x-csrf-token': document.querySelector('meta[name="csrf-token"]').content,
                    },
                })
                this.documentData = (this.documentData || []).filter((item) => String(item.id || '') !== String(doc.id))
                window.location.reload()
            } catch (error) {
                const message =
                    error?.response?.data?.message ||
                    'Unable to remove this upload right now. Please try again.'
                alert(message)
            }
        }
    },
};
</script>
