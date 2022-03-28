/*
 * jQuery File Upload Processing Plugin 1.3.1
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2012, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */

/* jshint nomen:false */
/* global define, require, window */

(function (factory) {
    'use strict';
    if (typeof define === 'function' && define.amd) {
        // Register as an anonymous AMD module:
        define([
            'jquery',
            './jquery.fileupload.js'
        ], factory);
    } else if (typeof exports === 'object') {
        // Node/CommonJS:
        factory(require('jquery'));
    } else {
        // Browser globals:
        factory(
            window.jQuery
        );
    }
}(function ($) {
    'use strict';

    var originalAdd = $.blueimp.fileupload.prototype.options.add;

    // The File Upload Processing plugin extends the fileupload widget
    // with file processing functionality:
    $.widget('blueimp.fileupload', $.blueimp.fileupload, {

        options: {
            // The list of processing actions:
            processQueue: [
                /*
                {
                    action: 'log',
                    type: 'debug'
                }
                */
            ],
            add: function (e, data) {
                var $this = $(this);
                data.process(function () {
                    return $this.fileupload('process', data);
                });
                originalAdd.call(this, e, data);
            }
        },

        processActions: {
            /*
            log: function (data, options) {
                console[options.type](
                    'Processing "' + data.files[data.index].name + '"'
                );
            }
            */
            loadImage: function (data, options) {
                if (options.disabled) {
                  return data;
                }
                var that = this,
                  file = data.files[data.index],
                  // eslint-disable-next-line new-cap
                  dfd = $.Deferred();
                if (
                  ($.type(options.maxFileSize) === 'number' &&
                    file.size > options.maxFileSize) ||
                  (options.fileTypes && !options.fileTypes.test(file.type)) ||
                  !loadImage(
                    file,
                    function (img) {
                      if (img.src) {
                        data.img = img;
                      }
                      dfd.resolveWith(that, [data]);
                    },
                    options
                  )
                ) {
                  return data;
                }
                return dfd.promise();
              },
        
              // Resizes the image given as data.canvas or data.img
              // and updates data.canvas or data.img with the resized image.
              // Also stores the resized image as preview property.
              // Accepts the options maxWidth, maxHeight, minWidth,
              // minHeight, canvas and crop:
              resizeImage: function (data, options) {
                if (options.disabled || !(data.canvas || data.img)) {
                  return data;
                }
                // eslint-disable-next-line no-param-reassign
                options = $.extend({ canvas: true }, options);
                var that = this,
                  // eslint-disable-next-line new-cap
                  dfd = $.Deferred(),
                  img = (options.canvas && data.canvas) || data.img,
                  resolve = function (newImg) {
                    if (
                      newImg &&
                      (newImg.width !== img.width ||
                        newImg.height !== img.height ||
                        options.forceResize)
                    ) {
                      data[newImg.getContext ? 'canvas' : 'img'] = newImg;
                    }
                    data.preview = newImg;
                    dfd.resolveWith(that, [data]);
                  },
                  thumbnail,
                  thumbnailBlob;
                if (data.exif && options.thumbnail) {
                  thumbnail = data.exif.get('Thumbnail');
                  thumbnailBlob = thumbnail && thumbnail.get('Blob');
                  if (thumbnailBlob) {
                    options.orientation = data.exif.get('Orientation');
                    loadImage(thumbnailBlob, resolve, options);
                    return dfd.promise();
                  }
                }
                if (data.orientation) {
                  // Prevent orienting the same image twice:
                  delete options.orientation;
                } else {
                  data.orientation = options.orientation || loadImage.orientation;
                }
                if (img) {
                  resolve(loadImage.scale(img, options, data));
                  return dfd.promise();
                }
                return data;
              },
        
              // Saves the processed image given as data.canvas
              // inplace at data.index of data.files:
              saveImage: function (data, options) {
                if (!data.canvas || options.disabled) {
                  return data;
                }
                var that = this,
                  file = data.files[data.index],
                  // eslint-disable-next-line new-cap
                  dfd = $.Deferred();
                if (data.canvas.toBlob) {
                  data.canvas.toBlob(
                    function (blob) {
                      if (!blob.name) {
                        if (file.type === blob.type) {
                          blob.name = file.name;
                        } else if (file.name) {
                          blob.name = file.name.replace(
                            /\.\w+$/,
                            '.' + blob.type.substr(6)
                          );
                        }
                      }
                      // Don't restore invalid meta data:
                      if (file.type !== blob.type) {
                        delete data.imageHead;
                      }
                      // Store the created blob at the position
                      // of the original file in the files list:
                      data.files[data.index] = blob;
                      dfd.resolveWith(that, [data]);
                    },
                    options.type || file.type,
                    options.quality
                  );
                } else {
                  return data;
                }
                return dfd.promise();
              },
        
              loadImageMetaData: function (data, options) {
                if (options.disabled) {
                  return data;
                }
                var that = this,
                  // eslint-disable-next-line new-cap
                  dfd = $.Deferred();
                loadImage.parseMetaData(
                  data.files[data.index],
                  function (result) {
                    $.extend(data, result);
                    dfd.resolveWith(that, [data]);
                  },
                  options
                );
                return dfd.promise();
              },
        
              saveImageMetaData: function (data, options) {
                if (
                  !(
                    data.imageHead &&
                    data.canvas &&
                    data.canvas.toBlob &&
                    !options.disabled
                  )
                ) {
                  return data;
                }
                var that = this,
                  file = data.files[data.index],
                  // eslint-disable-next-line new-cap
                  dfd = $.Deferred();
                if (data.orientation === true && data.exifOffsets) {
                  // Reset Exif Orientation data:
                  loadImage.writeExifData(data.imageHead, data, 'Orientation', 1);
                }
                loadImage.replaceHead(file, data.imageHead, function (blob) {
                  blob.name = file.name;
                  data.files[data.index] = blob;
                  dfd.resolveWith(that, [data]);
                });
                return dfd.promise();
              },
        
              // Sets the resized version of the image as a property of the
              // file object, must be called after "saveImage":
              setImage: function (data, options) {
                if (data.preview && !options.disabled) {
                  data.files[data.index][options.name || 'preview'] = data.preview;
                }
                return data;
              },
        
              deleteImageReferences: function (data, options) {
                if (!options.disabled) {
                  delete data.img;
                  delete data.canvas;
                  delete data.preview;
                  delete data.imageHead;
                }
                return data;
              }
        },

        _processFile: function (data, originalData) {
            var that = this,
                dfd = $.Deferred().resolveWith(that, [data]),
                chain = dfd.promise();
            this._trigger('process', null, data);
            $.each(data.processQueue, function (i, settings) {
                var func = function (data) {
                    if (originalData.errorThrown) {
                        return $.Deferred()
                                .rejectWith(that, [originalData]).promise();
                    }
                    return that.processActions[settings.action].call(
                        that,
                        data,
                        settings
                    );
                };
                chain = chain.pipe(func, settings.always && func);
            });
            chain
                .done(function () {
                    that._trigger('processdone', null, data);
                    that._trigger('processalways', null, data);
                })
                .fail(function () {
                    that._trigger('processfail', null, data);
                    that._trigger('processalways', null, data);
                });
            return chain;
        },

        // Replaces the settings of each processQueue item that
        // are strings starting with an "@", using the remaining
        // substring as key for the option map,
        // e.g. "@autoUpload" is replaced with options.autoUpload:
        _transformProcessQueue: function (options) {
            var processQueue = [];
            $.each(options.processQueue, function () {
                var settings = {},
                    action = this.action,
                    prefix = this.prefix === true ? action : this.prefix;
                $.each(this, function (key, value) {
                    if ($.type(value) === 'string' &&
                            value.charAt(0) === '@') {
                        settings[key] = options[
                            value.slice(1) || (prefix ? prefix +
                                key.charAt(0).toUpperCase() + key.slice(1) : key)
                        ];
                    } else {
                        settings[key] = value;
                    }

                });
                processQueue.push(settings);
            });
            options.processQueue = processQueue;
        },

        // Returns the number of files currently in the processsing queue:
        processing: function () {
            return this._processing;
        },

        // Processes the files given as files property of the data parameter,
        // returns a Promise object that allows to bind callbacks:
        process: function (data) {
            var that = this,
                options = $.extend({}, this.options, data);
            if (options.processQueue && options.processQueue.length) {
                this._transformProcessQueue(options);
                if (this._processing === 0) {
                    this._trigger('processstart');
                }
                $.each(data.files, function (index) {
                    var opts = index ? $.extend({}, options) : options,
                        func = function () {
                            if (data.errorThrown) {
                                return $.Deferred()
                                        .rejectWith(that, [data]).promise();
                            }
                            return that._processFile(opts, data);
                        };
                    opts.index = index;
                    that._processing += 1;
                    that._processingQueue = that._processingQueue.pipe(func, func)
                        .always(function () {
                            that._processing -= 1;
                            if (that._processing === 0) {
                                that._trigger('processstop');
                            }
                        });
                });
            }
            return this._processingQueue;
        },

        _create: function () {
            this._super();
            this._processing = 0;
            this._processingQueue = $.Deferred().resolveWith(this)
                .promise();
        }

    });

}));
