<template>
  <div>
    <div
      v-for="(document, index) in existingDocuments"
      :key="document.id || document.fullUrl || document.url || document.name || `existing-${index}`"
      :class="{ 'mb-2': (index + 1) == existingDocuments.length }"
    >
      <a download :href="document.fullUrl || document.url" class="link">{{ document.name }}</a>
    </div>
    <label for="documents" class="label form__label">*Upload files (Word, PDF, audio files, jpeg)<br/>Max filesize 5mb, if your file is larger than this, please contact us directly.</label>
    <vue-dropzone id="dropzone" :options="dropzoneOptions"
      @vdropzone-success="uploadedImage"
      @vdropzone-error="onError"
      @vdropzone-removed-file="deleteImage">
    </vue-dropzone>
    <div v-for="(document, index) in newDocuments" :key="document.url || document.name || `new-${index}`">
      <a :href="document.url" class="link">{{ document.name }}</a>
      <input type="hidden" :name="`documents[${index}][name]`" :value="document.name">
      <input type="hidden" :name="`documents[${index}][url]`" :value="document.url">
    </div>
  </div>
</template>

<script>
import vue2Dropzone from 'vue2-dropzone'
import 'vue2-dropzone/dist/vue2Dropzone.min.css'

export default {
  components: {
    vueDropzone: vue2Dropzone
  },
  props: {
    existingDocuments: Array
  },
  data() {
    return {
      dropzoneOptions: {
        url: '/api/documents',
        maxFilesize: 5,
        parallelUploads: 1,
        acceptedFiles: 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,audio/*,image/*',
        headers: {
          'x-csrf-token' : document.querySelector('meta[name="csrf-token"]').content
        },
        addRemoveLinks: true,
      },
      newDocuments: [],
      documentsData: this.existingDocuments
    }
  },
  methods: {
    uploadedImage(file, response) {
      let payload
      try {
        payload = typeof response === 'string' ? JSON.parse(response) : response
      } catch (e) {
        payload = {}
      }
      const name = payload.file_name || payload.name || (file && file.name) || ''
      const url = payload.file_path || payload.url || ''
      this.newDocuments.push({ name, url })
    },
    onError(file, message) {
      const msg = typeof message === 'string' ? message : (message && message.message) || 'Upload failed. Please check file type/size and try again.'
      try {
        const el = file && file.previewElement ? file.previewElement.querySelector('[data-dz-errormessage]') : null
        if (el) { el.textContent = msg }
      } catch (e) {}
      alert(msg)
    },
    deleteImage() {
      console.log('file deleted')
      // axios.delete('/files', {
      //   data: {
      //     'path': this.document,
      //   }
      // })
    }
  }
}
</script>
