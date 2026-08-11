<template>
  <div class="fct-card">
    <div class="fct-card-header border-bottom">
      <div class="flex-1 flex justify-between items-center gap-2">
        <h2 class="fct-card-header-title is-small">{{ payload.i18n.section_title }}</h2>
        <a
            :href="payload.urls.crm_profile"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-1 text-system-mid text-xs">
          {{ payload.i18n.view_in_crm }}
          <svg class="w-3 h-3 block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
          </svg>
        </a>
      </div>
    </div>

    <div class="fct-card-body">
      <div class="mb-4 p-3 bg-gray-50 rounded text-sm dark:bg-primary-500">
        <div v-if="payload.subscriber.full_name" class="mb-1">
          <span class="font-medium">{{ payload.i18n.name }}</span>
          <span class="ml-1">{{ payload.subscriber.full_name }}</span>
        </div>
        <div>
          <span class="font-medium">{{ payload.i18n.status }}</span>
          <el-tag
              :type="payload.subscriber.status === 'subscribed' ? 'success' : 'warning'"
              size="small"
              class="ml-1">
            {{ payload.subscriber.status }}
          </el-tag>
        </div>
      </div>

      <div v-if="payload.subscriber.stats" class="mb-4 p-3 bg-gray-50 rounded dark:bg-primary-500">
        <div class="flex items-center justify-center gap-3 text-sm text-gray-600">
          <el-tooltip effect="dark" :content="payload.i18n.emails_sent" placement="top">
            <div class="flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
              <span class="font-medium">{{ payload.subscriber.stats.emails }}</span>
            </div>
          </el-tooltip>

          <span class="text-gray-400">&bull;</span>

          <el-tooltip effect="dark" :content="payload.i18n.open_rate" placement="top">
            <div class="flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"/>
              </svg>
              <span class="font-medium">{{ ratePercent(payload.subscriber.stats.opens) }}</span>
            </div>
          </el-tooltip>

          <span class="text-gray-400">&bull;</span>

          <el-tooltip effect="dark" :content="payload.i18n.click_rate" placement="top">
            <div class="flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
              </svg>
              <span class="font-medium">{{ ratePercent(payload.subscriber.stats.clicks) }}</span>
            </div>
          </el-tooltip>
        </div>
      </div>

      <div v-for="segmentType in segmentTypes" :key="segmentType" class="mb-4">
        <div class="mb-2 font-medium text-gray-700">{{ payload.i18n[segmentType] }}</div>

        <div class="flex flex-wrap gap-2 items-center">
          <el-tag
              v-for="item in applied[segmentType]"
              :key="item.id"
              type="info"
              size="default">
            {{ item.title }}
            <el-popconfirm
                v-if="payload.permissions.can_manage"
                :title="removeQuestion(item)"
                :confirm-button-text="payload.i18n.remove"
                :cancel-button-text="payload.i18n.cancel"
                width="230"
                @confirm="detachItem(segmentType, item)">
              <template #reference>
                <el-button
                    text
                    size="small"
                    class="fcrm_fc_segment_remove"
                    :aria-label="removeLabel(item)"
                    style="padding: 0; min-height: auto; height: 14px; width: 14px; border: none; color: #525866;">
                  <svg aria-hidden="true" width="12" height="12" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.0001 8.93955L13.7126 5.22705L14.7731 6.28755L11.0606 10.0001L14.7731 13.7126L13.7126 14.7731L10.0001 11.0606L6.28755 14.7731L5.22705 13.7126L8.93955 10.0001L5.22705 6.28755L6.28755 5.22705L10.0001 8.93955Z" fill="currentColor"/>
                  </svg>
                </el-button>
              </template>
            </el-popconfirm>
          </el-tag>

          <el-popover
              v-if="payload.permissions.can_manage"
              :ref="el => (pickerRefs[segmentType] = el)"
              trigger="click"
              :width="280"
              @show="initPicker(segmentType)">
            <template #reference>
              <el-button size="small" circle style="padding: 4px;height: 28px;width: 28px;" :aria-label="payload.i18n.add">
                <svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M9.25 9.25V4.75H10.75V9.25H15.25V10.75H10.75V15.25H9.25V10.75H4.75V9.25H9.25Z" fill="currentColor"/>
                </svg>
              </el-button>
            </template>

            <div>
              <el-input
                  v-model="picker[segmentType].search"
                  size="small"
                  clearable
                  :placeholder="payload.i18n['search_' + segmentType]"
                  style="margin-bottom: 10px;"/>

              <el-checkbox-group
                  v-if="filteredOptions(segmentType).length"
                  v-model="picker[segmentType].selected"
                  class="flex flex-col gap-1"
                  style="max-height: 300px; overflow-y: auto;">
                <el-checkbox
                    v-for="option in filteredOptions(segmentType)"
                    :key="option.id"
                    :value="option.id"
                    style="display: flex; width: 100%; margin: 0 0 6px 0;">
                  {{ option.title }}
                </el-checkbox>
              </el-checkbox-group>
              <p v-else class="text-sm text-gray-500">{{ payload.i18n['no_' + segmentType] }}</p>

              <div class="mt-3">
                <el-button
                    v-if="filteredOptions(segmentType).length"
                    type="primary"
                    size="small"
                    style="width: 100%;"
                    :loading="picker[segmentType].saving"
                    @click="applySelection(segmentType)">
                  {{ payload.i18n.apply }}
                </el-button>
                <el-button
                    v-else-if="canCreate(segmentType)"
                    type="primary"
                    size="small"
                    style="width: 100%;"
                    :loading="picker[segmentType].creating"
                    @click="createAndAttach(segmentType)">
                  {{ payload.i18n.add_new }} - {{ picker[segmentType].search }}
                </el-button>
              </div>
            </div>
          </el-popover>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
/* global Rest, Notify */
// Runs inside FluentCart's constrained vue-template runtime: only the
// injected Rest / Notify globals are available, and the whole script is
// scanned against a blocklist - keep names and comments plain. Talk to the
// server through the injected Rest / Notify only; do not reach for
// FluentCart's app mixin (this.$post / this.handleSuccess) - it is not part
// of the sandbox contract and could be scoped away from dynamic widgets.
export default {
  name: 'FluentCrmContactWidget',
  // FluentCart mounts us with v-bind="{...widget}", so every non-prop key -
  // including the whole raw component source - would otherwise land on the
  // root element as a stray attribute.
  inheritAttrs: false,
  props: {
    payload: {
      type: Object,
      required: true
    },
    data: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      segmentTypes: ['lists', 'tags'],
      // Currently attached segments, kept in sync with the server response
      applied: {
        lists: [],
        tags: []
      },
      // Per-type picker state for the popover
      picker: {
        lists: {search: '', selected: [], saving: false, creating: false},
        tags: {search: '', selected: [], saving: false, creating: false}
      },
      // Local copies so newly created segments become selectable
      options: {
        lists: [],
        tags: []
      },
      pickerRefs: {}
    };
  },
  created() {
    this.applied.lists = (this.payload.applied && this.payload.applied.lists) || [];
    this.applied.tags = (this.payload.applied && this.payload.applied.tags) || [];
    this.options.lists = (this.payload.options && this.payload.options.lists) || [];
    this.options.tags = (this.payload.options && this.payload.options.tags) || [];
  },
  methods: {
    ratePercent(count) {
      const totalEmails = this.payload.subscriber.stats.emails;
      if (!totalEmails || !count) {
        return '0%';
      }
      return ((count / totalEmails) * 100).toFixed(2) + '%';
    },
    removeQuestion(item) {
      return this.payload.i18n.remove_question.replace('%s', item.title);
    },
    // Accessible name for the remove button (the visible glyph is aria-hidden)
    removeLabel(item) {
      return this.payload.i18n.remove_item.replace('%s', item.title);
    },
    filteredOptions(type) {
      const search = this.picker[type].search.toLowerCase().trim();
      if (!search) {
        return this.options[type];
      }
      return this.options[type].filter(option => option.title.toLowerCase().includes(search));
    },
    canCreate(type) {
      return this.payload.permissions.can_create && !!this.picker[type].search.trim();
    },
    initPicker(type) {
      this.picker[type].search = '';
      this.picker[type].selected = this.applied[type].map(item => item.id);
    },
    closePicker(type) {
      const pop = this.pickerRefs[type];
      if (pop && pop.hide) {
        pop.hide();
      }
    },
    applySelection(type) {
      const currentIds = this.applied[type].map(item => item.id);
      const chosenIds = this.picker[type].selected;

      const attach = chosenIds.filter(id => !currentIds.includes(id));
      const detach = currentIds.filter(id => !chosenIds.includes(id));

      if (!attach.length && !detach.length) {
        this.closePicker(type);
        return;
      }

      this.syncSegments(type, attach, detach);
    },
    detachItem(type, item) {
      this.syncSegments(type, [], [item.id]);
    },
    // Single round trip handles both attach and detach; ids avoid
    // slug edge cases. The response carries the refreshed subscriber,
    // which becomes the new local state.
    syncSegments(type, attach, detach) {
      const body = {
        type: type,
        find_by: 'id',
        subscribers: [this.payload.subscriber.id]
      };
      if (attach.length) {
        body.attach = attach;
      }
      if (detach.length) {
        body.detach = detach;
      }

      this.picker[type].saving = true;

      Rest.post('subscribers/sync-segments', body, this.payload.urls.rest_base)
          .then(response => {
            const updated = response.subscribers && response.subscribers[0];
            if (updated) {
              this.applied.lists = (updated.lists || []).map(item => ({id: item.id, title: item.title}));
              this.applied.tags = (updated.tags || []).map(item => ({id: item.id, title: item.title}));
            }
            Notify.success(response.message || this.payload.i18n.updated);
            this.closePicker(type);
          })
          .catch(errors => {
            this.safeNotifyError((errors && errors.data && errors.data.message) || this.payload.i18n.failed);
          })
          .finally(() => {
            this.picker[type].saving = false;
          });
    },
    // Creates the tag or list via the matching CRM endpoint, then
    // attaches it to this contact right away
    createAndAttach(type) {
      const title = this.picker[type].search.trim();
      if (!title) {
        return;
      }

      this.picker[type].creating = true;

      Rest.post(type, {title: title}, this.payload.urls.rest_base)
          .then(response => {
            const created = response.item;
            if (!created) {
              this.safeNotifyError(this.payload.i18n.failed);
              return;
            }
            this.options[type].push({id: created.id, title: created.title});
            this.syncSegments(type, [created.id], []);
          })
          .catch(errors => {
            this.safeNotifyError((errors && errors.data && errors.data.message) || this.payload.i18n.failed);
          })
          .finally(() => {
            this.picker[type].creating = false;
          });
    },
    safeNotifyError(message) {
      Notify.error(this.stripHtml(message || this.payload.i18n.failed));
    },
    stripHtml(message) {
      return String(message).replace(/<[^>]*>/g, '');
    }
  }
};
</script>
