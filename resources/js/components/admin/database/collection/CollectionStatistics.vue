<!--
  - PhpMongoAdmin (www.phpmongoadmin.com) by Masterforms Mobile & Web (MFMAW)
  - @version      DocumentDuplicate.vue 1001 8/8/20, 10:23 pm  Gilbert Rehling $
  - @package      PhpMongoAdmin\resources
  - @subpackage   DocumentDuplicate.vue
  - @link         https://github.com/php-mongo/admin PHP MongoDB Admin
  - @copyright    Copyright (c) 2020. Gilbert Rehling of MMFAW. All rights reserved. (www.mfmaw.com)
  - @licence      PhpMongoAdmin is an Open Source Project released under the GNU GPLv3 license model.
  - @author       Gilbert Rehling:  gilbert@phpmongoadmin.com (www.gilbert-rehling.com)
  -  php-mongo-admin - License conditions:
  -  Contributions to our suggestion box are welcome: https://phpmongotools.com/suggestions
  -  This web application is available as Free Software and has no implied warranty or guarantee of usability.
  -  See licence.txt for the complete licensing outline.
  -  See https://www.gnu.org/licenses/license-list.html for information on GNU General Public License v3.0
  -  See COPYRIGHT.php for copyright notices and further details.
  -->
<style lang="scss">
    @import '~@/abstracts/_variables.scss';

    div.document-statistics-container {
        position: fixed;
        z-index: 999999;
        left: 10vw;
        right: 0;
        top: 0;

        div.document-statistics {
            background: $white;
            box-shadow: 0 0 4px 0 rgba(0,0,0,0.12), 0 4px 4px 0 rgba(0,0,0,0.24);
            border-left: 5px solid $orange;
            border-right: 5px solid $orange;
            color: $noticeColor;
            font-family: "Lato", sans-serif;
            font-size: 16px;
            line-height: 60px;
            margin: auto auto auto auto;
            max-height: 100vh;
            max-width: 800px;
            min-height: 50vh;
            min-width: 400px;
            overflow-y: auto;
            padding: 0 3rem 3rem 3rem;

            .modal-header {
                background-color: $lightGreyColor;
                height: 33px;
                margin: 0 -3rem 0 -3rem;
                max-width: 790px;
                padding: 0.55rem 20px 0 0;
                position: fixed;
                width: 100%;

                span.msg {
                    background-color: $offWhite;
                    border-radius: 5px;
                    left: 30px;
                    max-height: 25px;
                    padding: 2px 5px;
                    position: absolute;
                    top: 5px;

                    span.error {
                        color: $red;
                        position: relative;
                        top: -21px;
                    }

                    span.action {
                        color: $green;
                        position: relative;
                        top: -21px;
                    }
                }

                span.close {
                    cursor: pointer;
                }

                img {
                    vertical-align: top;
                }
            }

            h3 {
                margin-top: 40px;
            }

            ul {
                list-style: none;

                li {
                    background-color: $lightGrey;
                    border-bottom: 1px solid $darkGreyColor;
                    margin-bottom: 5px;
                    padding: 2px 0 2px 5px;

                    .title {
                        display: inline-block;
                        min-width: 150px;
                    }

                    .data {
                        display: inline-block;
                        max-width: 100%;
                        overflow-x: auto;
                        vertical-align: bottom;
                    }
                }
            }

        }
    }
</style>

<template>
    <transition name="slide-in-top">
        <div class="document-statistics-container" v-if="show">
            <div class="document-statistics">
                <div class="modal-header">
                    <span class="msg" v-show="errorMessage || actionMessage">
                        <span class="error">{{ errorMessage }}</span>
                        <span class="action">{{ actionMessage }}</span>
                    </span>
                    <span class="close u-pull-right" v-on:click="hideComponent">
                        <img src="/img/icon/cross-red.png" />
                    </span>
                </div>
                <h3 v-text="showLanguage('collection','collectionStatistics')"></h3>
                <ul>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'namespace')"></span>
                            <span class="data">{{ statistics.ns }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'documentCount')"></span>
                            <span class="data">{{ statistics.count }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'capped')"></span>
                            <span class="data">{{ statistics.capped }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('database', 'avgObjSize')"></span>
                            <span class="data">{{ statistics.avgObjSize }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'ok')"></span>
                            <span class="data">{{ statistics.ok }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'dataSize')"></span>
                            <span class="data">{{ statistics.size }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('database', 'storageSize')"></span>
                            <span class="data">{{ statistics.storageSize }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('database', 'indexSize')"></span>
                            <span class="data">{{ statistics.totalIndexSize }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'indexesNumber')"></span>
                            <span class="data">{{ statistics.nindexes }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <strong v-text="showLanguage('collection', 'indexDetails')"></strong>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'indexType')"></span>
                            <span class="data">{{ statistics.indexDetails._id_.type }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'indexUri')"></span>
                            <span class="data">{{ statistics.indexDetails._id_.uri }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'indexCreationString')"></span>
                            <span class="data">{{ statistics.indexDetails._id_.creationString }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'indexSizes')"></span>
                            <span class="data">{{ statistics.indexSizes._id_ }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <strong v-text="showLanguage('collection', 'indexMetadata')"></strong>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'indexMetaFormat')"></span>
                            <span class="data">{{ statistics.indexDetails._id_.metadata.formatVersion }}</span>
                        </p>
                    </li>
                    <li>
                        <p>
                            <span class="title" v-text="showLanguage('collection', 'indexMetaInfo')"></span>
                            <span class="data">{{ statistics.indexDetails._id_.metadata.infoObj }}</span>
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </transition>
</template>

<script>
    /*
     * Imports the Event Bus to pass events on tag updates
     */
    import { EventBus } from '../../../../event-bus.js';

    export default {
        /*
         *  Defines the data required by this component.
         */
        data() {
            return {
                actionMessage: null,
                collection: null,
                database: null,
                errorMessage: null,
                index: 0,
                limit: 75, // limit the status check iterations
                show: false,
                statistics: {}
            }
        },

        methods: {
            /*
             *   Calls the Translation and Language service
             */
            showLanguage( context, key, str ) {
                if (str) {
                    let string = this.$store.getters.getLanguageString( context, key );
                    return string.replace("%s", str);
                }
                return this.$store.getters.getLanguageString( context, key );
            },

            getStatistics(data) {
                this.database   = data.db;
                this.collection = data.coll;
                this.statistics = this.$store.getters.getCollectionStats;
               // data = {database: this.database, collection: this.collection };
                //this.$store.dispatch('getQueryLogs', data);
               // this.handleQueryLogs();
            },

            handleQueryLogs() {
                let status = this.$store.getters.getQueryLogsLoadStatus;
                console.log("status: " + status);
                if (status === 1 && this.index < this.limit) {
                    this.index += 1;
                    let self = this;
                    setTimeout(function() {
                        self.handleQueryLogs();
                    }, 100);

                }
                else if (status === 2) {
                    // success!
                    this.queryLogs = this.$store.getters.getQueryLogs;
                    if ( this.queryLogs.length > 0) {
                        this.actionMessage = this.showLanguage('collection', 'logsAction', this.queryLogs.length);
                    } else {
                        this.actionMessage = this.showLanguage('collection', 'logsActionEmpty');
                    }
                }
                else if (status === 3) {
                    this.errorMessage = this.showLanguage('collection', 'logsActionError');
                }
            },

            /*
             *   Show component
             */
            showComponent() {
                this.show = true;
            },

            /*
             *   Hide component
             */
            hideComponent() {
                this.show = false;
            }
        },

        /*
          Sets up the component when mounted.
        */
        mounted() {
            /*
             * On event, show the collection stats modal
             */
            EventBus.$on('show-document-statistics', ( data ) => {
                this.getStatistics(data);
                this.showComponent();
            });
        }
    }
</script>